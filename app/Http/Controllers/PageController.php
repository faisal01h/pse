<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Pages\PageHtml;
use Inertia\Inertia;
use Inertia\Response;

class PageController extends Controller
{
    public function show(Page $page): Response
    {
        $html = $page->format === Page::FORMAT_MARKDOWN
            ? PageHtml::fromMarkdown($page->body)
            : PageHtml::sanitize($page->body);

        return Inertia::render('Page', [
            'page' => [
                'title' => $page->title,
                'html' => $html,
            ],
        ]);
    }
}
