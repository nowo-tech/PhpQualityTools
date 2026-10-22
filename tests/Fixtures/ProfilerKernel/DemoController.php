<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Tests\Fixtures\ProfilerKernel;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Pages whose "query" count is constant, grows with the rows (N+1) or shrinks, plus a translated page.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final readonly class DemoController
{
    public function __construct(
        private RowStore $rows,
        private QueryLog $log,
        private TranslatorInterface $translator,
    ) {
    }

    public function constant(): Response
    {
        $this->log->count += 2;

        return $this->list();
    }

    public function nPlusOne(): Response
    {
        $this->log->count += 1 + $this->rows->count();

        return $this->list();
    }

    public function fewer(): Response
    {
        $this->log->count += $this->rows->count() > 5 ? 1 : 3;

        return $this->list();
    }

    public function missing(): Response
    {
        return new Response('gone', 404);
    }

    public function translated(): Response
    {
        $text = [
            $this->translator->trans('greeting'),
            $this->translator->trans('missing.title'),
            $this->translator->trans('missing.title'),
            $this->translator->trans('Save', [], 'admin'),
            $this->translator->trans('legacy.one', [], 'admin', 'es'),
        ];

        return new Response('<html><body><p>' . implode('</p><p>', array_map(htmlspecialchars(...), $text)) . '</p></body></html>');
    }

    private function list(): Response
    {
        $items = '';
        for ($i = 0; $i < $this->rows->count(); ++$i) {
            $items .= '<li>Row ' . $i . '</li>';
        }

        return new Response('<html><body><ul>' . $items . '</ul></body></html>');
    }
}
