<?php

declare(strict_types=1);

namespace App\Template\StaticPageLayout;

use Distantmagic\Docs\Template\Component\StaticPageBreadcrumbs;
use Distantmagic\Docs\Template\StaticPageLayout as BaseStaticPageLayout;
use Distantmagic\Resonance\Attribute\Singleton;
use Distantmagic\Resonance\Attribute\StaticPageLayout;
use Distantmagic\Resonance\EsbuildMeta;
use Distantmagic\Resonance\EsbuildMetaBuilder;
use Distantmagic\Resonance\EsbuildMetaEntryPoints;
use Distantmagic\Resonance\EsbuildMetaPreloadsRenderer;
use Distantmagic\Resonance\SingletonCollection;
use Distantmagic\Resonance\StaticPage;
use Distantmagic\Resonance\StaticPageAggregate;
use Distantmagic\Resonance\StaticPageConfiguration;
use Distantmagic\Resonance\StaticPageContentRenderer;
use Ds\PriorityQueue;
use Generator;

#[Singleton(collection: SingletonCollection::StaticPageLayout)]
#[StaticPageLayout('modernphp:page')]
readonly class Page extends BaseStaticPageLayout
{
    private StaticPageBreadcrumbs $breadcrumbs;
    private EsbuildMeta $esbuildMeta;

    public function __construct(
        private EsbuildMetaBuilder $esbuildMetaBuilder,
        StaticPageAggregate $staticPageAggregate,
        StaticPageConfiguration $staticPageConfiguration,
        private StaticPageContentRenderer $staticPageContentRenderer,
    ) {
        $this->breadcrumbs = new StaticPageBreadcrumbs($staticPageAggregate->staticPages);
        $this->esbuildMeta = $this->esbuildMetaBuilder->build(
            $staticPageConfiguration->esbuildMetafile,
            $staticPageConfiguration->stripOutputPrefix,
        );
    }

    /**
     * @return Generator<string>
     */
    public function renderStaticPage(StaticPage $staticPage): Generator
    {
        $esbuildMetaEntryPoints = new EsbuildMetaEntryPoints($this->esbuildMeta);
        $esbuildPreloadsRenderer = new EsbuildMetaPreloadsRenderer($esbuildMetaEntryPoints);

        $renderedScripts = $this->renderScripts($esbuildMetaEntryPoints);
        $renderedStylesheets = $this->renderStylesheets($staticPage, $esbuildMetaEntryPoints);
        $renderedPreloads = $esbuildPreloadsRenderer->render();

        $currentYear = date('Y');

        yield <<<'HTML'
        <!DOCTYPE html>
        <html lang="en">
            <head>
                <meta charset="utf-8">
                <meta name="viewport" content="width=device-width, initial-scale=1">
                <meta name="turbo-refresh-method" content="morph">
                <meta name="turbo-refresh-scroll" content="preserve">
                <title>PHP Goodness</title>
                <link rel="preconnect" href="https://fonts.googleapis.com">
                <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
                <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Mono:wght@100..900&family=Noto+Sans:ital,wght@0,100..900;1,100..900&family=Noto+Serif:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
        HTML;
        yield $renderedPreloads;
        yield $renderedStylesheets;
        yield $renderedScripts;
        yield <<<'HTML'
        </head>
        <body>
            <div class="body-content">
                <nav class="primary-navigation"></nav>
                <main class="primary-content-wrapper">
        HTML;
        yield <<<'HTML'
                    <nav class="breadcrumbs documentation__breadcrumbs">
        HTML;
        yield from $this->breadcrumbs->render($staticPage);
        yield <<<'HTML'
                    </nav>
                    <div class="primary-content formatted-content">
        HTML;
        yield from $this->renderBodyContent($staticPage);
        yield <<<HTML
                    </div>
                </main>
                <footer class="primary-footer">
                    <div class="primary-footer__copyright">
                        Copyright &copy; {$currentYear}
                        Built with
                        <a href="https://resonance.distantmagic.com">Resonance</a>
                    </div>
                </footer>
            </div>
        </body>
        </html>
        HTML;
    }

    /**
     * @param PriorityQueue<string> $scripts
     */
    protected function registerScripts(PriorityQueue $scripts): void
    {
        $scripts->push('global_turbo.ts', 900);
        $scripts->push('global_stimulus.ts', 800);
        $scripts->push('controller_graphviz.ts', 0);
        $scripts->push('controller_hljs.ts', 0);
    }

    /**
     * @param PriorityQueue<string> $stylesheets
     */
    protected function registerStylesheets(PriorityQueue $stylesheets): void
    {
        $stylesheets->push('docs-common.css', 1000);
        $stylesheets->push('docs-hljs.css', 1000);
    }

    /**
     * @return Generator<string>
     */
    protected function renderBodyContent(StaticPage $staticPage): Generator
    {
        yield $this->staticPageContentRenderer->renderContent($staticPage);
    }

    private function renderScripts(EsbuildMetaEntryPoints $esbuildMetaEntryPoints): string
    {
        /**
         * @var PriorityQueue<string> $scripts
         */
        $scripts = new PriorityQueue();

        $this->registerScripts($scripts);

        $ret = '';

        foreach ($scripts as $script) {
            $ret .= sprintf(
                '<script defer type="module" src="%s"></script>'."\n",
                '/'.$esbuildMetaEntryPoints->resolveEntryPointPath($script),
            );
        }

        return $ret;
    }

    private function renderStylesheets(
        StaticPage $staticPage,
        EsbuildMetaEntryPoints $esbuildMetaEntryPoints,
    ): string {
        /**
         * @var PriorityQueue<string> $stylesheets
         */
        $stylesheets = new PriorityQueue();

        $this->registerStylesheets($stylesheets);

        foreach ($staticPage->frontMatter->registerStylesheets as $stylesheet) {
            $stylesheets->push($stylesheet, 0);
        }

        $ret = '';

        foreach ($stylesheets as $stylesheet) {
            $ret .= sprintf(
                '<link rel="stylesheet" href="%s">'."\n",
                '/'.$esbuildMetaEntryPoints->resolveEntryPointPath($stylesheet),
            );
        }

        return $ret;
    }
}
