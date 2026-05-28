<?php

declare(strict_types=1);

namespace App\Application\Docs;

use Illuminate\Support\Facades\Cache;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\Autolink\AutolinkExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\FrontMatter\FrontMatterExtension;
use League\CommonMark\Extension\FrontMatter\Output\RenderedContentWithFrontMatter;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\Extension\TableOfContents\TableOfContentsExtension;
use League\CommonMark\MarkdownConverter;

/**
 * File-backed Markdown docs.
 *
 * Sources live under `documentation/<locale>/` and are rendered through a
 * single CommonMark converter wired with GFM + heading anchors + ToC +
 * frontmatter parsing. Rendered HTML is cached per (slug, locale, mtime)
 * so editing a .md file invalidates that file's cache automatically.
 */
final class DocsService
{
    /**
     * Slug pattern : letters/digits/dash/slash. Reject anything else to
     * keep `../` out of `pathFor()`.
     */
    private const SLUG_PATTERN = '/^[a-z0-9-]+(\/[a-z0-9-]+)*$/i';

    private const CACHE_TTL = 3600;

    private readonly MarkdownConverter $converter;

    public function __construct()
    {
        $env = new Environment([
            'heading_permalink' => [
                'html_class' => 'tf-heading-permalink',
                'symbol' => '#',
                'insert' => 'before',
            ],
            'table_of_contents' => [
                'placeholder' => '[[TOC]]',
                'html_class' => 'tf-toc',
                'position' => 'placeholder',
                'min_heading_level' => 2,
                'max_heading_level' => 3,
                'normalize' => 'relative',
            ],
        ]);
        $env->addExtension(new CommonMarkCoreExtension());
        $env->addExtension(new GithubFlavoredMarkdownExtension());
        $env->addExtension(new HeadingPermalinkExtension());
        $env->addExtension(new FrontMatterExtension());
        $env->addExtension(new TableOfContentsExtension());
        $env->addExtension(new AutolinkExtension());

        $this->converter = new MarkdownConverter($env);
    }

    public function locales(): array
    {
        $root = base_path('documentation');
        if (! is_dir($root)) {
            return [];
        }

        return array_values(array_filter(
            scandir($root) ?: [],
            fn (string $entry): bool => $entry !== '.' && $entry !== '..' && is_dir($root.'/'.$entry),
        ));
    }

    /**
     * Build the sidebar tree for a locale.
     *
     * @return list<array{slug: string, title: string, order: int, children: list<mixed>}>
     */
    public function tree(string $locale): array
    {
        return Cache::remember(
            "docs:tree:{$locale}:".$this->treeSignature($locale),
            self::CACHE_TTL,
            fn (): array => $this->buildTree($locale),
        );
    }

    /**
     * Render a single doc. Returns null when the slug doesn't resolve
     * to a file under `documentation/<locale>/`.
     *
     * The cache stores plain strings/arrays only — caching the Doc VO
     * directly trips on __PHP_Incomplete_Class if the autoloader hasn't
     * loaded the class by the time the cached value is deserialised.
     */
    public function render(string $slug, string $locale): ?Doc
    {
        if ($slug === '' || preg_match(self::SLUG_PATTERN, $slug) !== 1) {
            return null;
        }

        $path = $this->pathFor($slug, $locale);
        if ($path === null) {
            return null;
        }

        $mtime = filemtime($path) ?: 0;
        $payload = Cache::remember(
            "docs:doc:{$locale}:{$slug}:{$mtime}",
            self::CACHE_TTL,
            fn (): array => $this->renderFile($slug, $path),
        );

        return new Doc(
            slug: $payload['slug'],
            title: $payload['title'],
            html: $payload['html'],
            toc: $payload['toc'],
            meta: $payload['meta'],
        );
    }

    /**
     * Resolve a slug to an absolute file path, or null if not found.
     * The slug regex above rejects path traversal — this is a second
     * line of defence via realpath comparison.
     */
    public function pathFor(string $slug, string $locale): ?string
    {
        $root = realpath(base_path('documentation/'.$locale));
        if ($root === false) {
            return null;
        }

        $candidate = realpath($root.'/'.$slug.'.md');
        if ($candidate === false || ! str_starts_with($candidate, $root.'/')) {
            return null;
        }

        return $candidate;
    }

    public function defaultSlug(string $locale): string
    {
        return $this->pathFor('index', $locale) !== null ? 'index' : 'getting-started';
    }

    /**
     * Compute a hash over the directory listing so the tree cache busts
     * whenever a .md file is added/removed/renamed.
     */
    private function treeSignature(string $locale): string
    {
        $root = base_path('documentation/'.$locale);
        if (! is_dir($root)) {
            return 'empty';
        }

        $files = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            /** @var \SplFileInfo $file */
            if ($file->isFile() && $file->getExtension() === 'md') {
                $files[] = $file->getRealPath().':'.$file->getMTime();
            }
        }
        sort($files);

        return substr(md5(implode('|', $files)), 0, 12);
    }

    /**
     * @return list<array{slug: string, title: string, order: int, children: list<mixed>}>
     */
    private function buildTree(string $locale): array
    {
        $root = base_path('documentation/'.$locale);
        if (! is_dir($root)) {
            return [];
        }

        $items = $this->scanDir($root, $locale, '');

        $this->sortTree($items);

        return $items;
    }

    /**
     * @return list<array{slug: string, title: string, order: int, children: list<mixed>}>
     */
    private function scanDir(string $dir, string $locale, string $prefix): array
    {
        $items = [];

        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $abs = $dir.'/'.$entry;

            if (is_dir($abs)) {
                $children = $this->scanDir($abs, $locale, $prefix.$entry.'/');
                $indexSlug = $prefix.$entry.'/index';
                $indexPath = $this->pathFor($indexSlug, $locale);

                if ($indexPath !== null) {
                    $meta = $this->frontMatterFor($indexPath);
                    $items[] = [
                        'slug' => $indexSlug,
                        'title' => $meta['title'] ?? $this->humanize($entry),
                        'order' => (int) ($meta['order'] ?? 100),
                        'children' => $children,
                    ];
                } else {
                    $items[] = [
                        'slug' => '',
                        'title' => $this->humanize($entry),
                        'order' => 100,
                        'children' => $children,
                    ];
                }
                continue;
            }

            if (! is_file($abs) || ! str_ends_with($entry, '.md')) {
                continue;
            }

            $slug = $prefix.substr($entry, 0, -3);
            if (str_ends_with($slug, '/index')) {
                continue; // index files are folded into their parent dir
            }

            $meta = $this->frontMatterFor($abs);
            $items[] = [
                'slug' => $slug,
                'title' => $meta['title'] ?? $this->humanize(substr($entry, 0, -3)),
                'order' => (int) ($meta['order'] ?? 100),
                'children' => [],
            ];
        }

        return $items;
    }

    private function sortTree(array &$items): void
    {
        usort($items, function (array $a, array $b): int {
            return ($a['order'] <=> $b['order']) ?: strcmp($a['title'], $b['title']);
        });
        foreach ($items as &$item) {
            if ($item['children'] !== []) {
                $this->sortTree($item['children']);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function frontMatterFor(string $path): array
    {
        $contents = (string) file_get_contents($path);
        $result = $this->converter->convert($contents);

        if ($result instanceof RenderedContentWithFrontMatter) {
            $fm = $result->getFrontMatter();

            return is_array($fm) ? $fm : [];
        }

        return [];
    }

    /**
     * @return array{slug: string, title: string, html: string, toc: string, meta: array<string, mixed>}
     */
    private function renderFile(string $slug, string $path): array
    {
        $contents = (string) file_get_contents($path);
        $result = $this->converter->convert($contents);
        $html = $this->rewriteRelativeLinks((string) $result);
        $meta = $result instanceof RenderedContentWithFrontMatter ? (array) $result->getFrontMatter() : [];

        // ToC right rail : prepend [[TOC]] to the body WITHOUT the
        // frontmatter (otherwise the YAML frontmatter survives as a
        // CommonMark paragraph and pollutes the ToC output with
        // "title: foo / order: 1" lines).
        $body = $this->stripFrontMatter($contents);
        $tocResult = $this->converter->convert("[[TOC]]\n\n".$body);
        $tocHtml = '';
        if (preg_match('/<ul class="tf-toc">.*?<\/ul>/s', (string) $tocResult, $m) === 1) {
            $tocHtml = $this->rewriteRelativeLinks($m[0]);
        }

        $title = (string) ($meta['title'] ?? $this->extractH1($html) ?? $this->humanize(basename($slug)));

        return [
            'slug' => $slug,
            'title' => $title,
            'html' => $html,
            'toc' => $tocHtml,
            'meta' => $meta,
        ];
    }

    /**
     * Rewrite relative markdown links so they resolve from the docs
     * root regardless of the current page's depth. Without this,
     * `[Troubleshooting](self-hosting/troubleshooting)` from
     * `/docs/self-hosting/upgrading` would resolve as
     * `/docs/self-hosting/self-hosting/troubleshooting` — broken.
     *
     * Skipped : absolute (`/…`), anchors (`#…`), and full schemes
     * (`http://`, `https://`, `mailto:`, etc.).
     */
    private function rewriteRelativeLinks(string $html): string
    {
        return (string) preg_replace_callback(
            '/href="([^"]+)"/',
            function (array $m): string {
                $href = $m[1];
                // Absolute, anchor, scheme — leave it alone.
                if ($href === '' || $href[0] === '/' || $href[0] === '#' || preg_match('/^[a-z][a-z0-9+\-.]*:/i', $href) === 1) {
                    return 'href="'.$href.'"';
                }
                // Drop optional .md (we route on slugs, not file names).
                $href = preg_replace('/\.md(?=$|[#?])/', '', $href);

                return 'href="/docs/'.$href.'"';
            },
            $html
        );
    }

    private function stripFrontMatter(string $contents): string
    {
        return (string) preg_replace('/\A---\R.*?\R---\R/s', '', $contents);
    }

    private function extractH1(string $html): ?string
    {
        if (preg_match('/<h1\b[^>]*>(.*?)<\/h1>/is', $html, $m) === 1) {
            return trim(strip_tags($m[1]));
        }

        return null;
    }

    private function humanize(string $slug): string
    {
        return ucfirst(str_replace(['-', '_'], ' ', $slug));
    }
}
