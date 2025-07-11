<?php
// app/Console/Commands/GenerateHelpSitemap.php - Command for SEO

namespace App\Console\Commands;

use App\Models\FAQ;
use App\Models\HelpCategory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class GenerateHelpSitemap extends Command
{
    protected $signature = 'help:generate-sitemap';
    protected $description = 'Generate sitemap for help system SEO';

    public function handle(): int
    {
        $this->info('Generating help system sitemap...');

        $xml = $this->generateSitemapXML();
        
        Storage::disk('public')->put('sitemap-help.xml', $xml);
        
        $this->info('Help sitemap generated successfully at: ' . storage_path('app/public/sitemap-help.xml'));
        
        return Command::SUCCESS;
    }

    private function generateSitemapXML(): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        // Add help index page
        $xml .= $this->addUrl(url('/help'), now(), 'weekly', '1.0');

        // Add categories
        HelpCategory::active()->orderBy('sort_order')->chunk(100, function ($categories) use (&$xml) {
            foreach ($categories as $category) {
                $xml .= $this->addUrl(
                    url("/help/category/{$category->slug}"),
                    $category->updated_at,
                    'weekly',
                    '0.8'
                );
            }
        });

        // Add FAQs
        FAQ::published()->with('category')->chunk(100, function ($faqs) use (&$xml) {
            foreach ($faqs as $faq) {
                $xml .= $this->addUrl(
                    url("/help/faq/{$faq->id}"),
                    $faq->updated_at,
                    'monthly',
                    '0.6'
                );
            }
        });

        $xml .= '</urlset>';

        return $xml;
    }

    private function addUrl(string $loc, $lastmod, string $changefreq, string $priority): string
    {
        return sprintf(
            "  <url>\n    <loc>%s</loc>\n    <lastmod>%s</lastmod>\n    <changefreq>%s</changefreq>\n    <priority>%s</priority>\n  </url>\n",
            htmlspecialchars($loc),
            $lastmod->toISOString(),
            $changefreq,
            $priority
        );
    }
}