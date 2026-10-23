<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class SeoController extends Controller
{
    public function sitemap(): Response
    {
        $urls = [
            [
                'loc' => route('home'),
                'changefreq' => 'weekly',
                'priority' => '1.0',
            ],
        ];

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        foreach ($urls as $url) {
            $xml .= "    <url>\n";
            $xml .= '        <loc>'.e($url['loc'])."</loc>\n";
            $xml .= '        <changefreq>'.e($url['changefreq'])."</changefreq>\n";
            $xml .= '        <priority>'.e($url['priority'])."</priority>\n";
            $xml .= "    </url>\n";
        }

        $xml .= '</urlset>'."\n";

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }

    public function robots(): Response
    {
        $lines = [
            'User-agent: *',
            'Allow: /',
            'Disallow: /dashboard',
            'Disallow: /boards',
            'Disallow: /links',
            'Disallow: /settings',
            'Disallow: /attachments',
            'Disallow: /auth/',
            'Disallow: /d/',
            'Disallow: /up',
            '',
            'Sitemap: '.route('seo.sitemap'),
        ];

        return response(implode("\n", $lines)."\n", 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }
}
