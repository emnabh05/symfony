<?php

namespace App\Command;

use App\Entity\Supplement;
use App\Repository\SupplementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:supplements:import-house',
    description: 'Import supplements and images from House Nutrition sitemap.',
)]
class ImportHouseNutritionSupplementsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SupplementRepository $supplementRepository,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Maximum number of products to import', '40')
            ->addOption('sitemap', null, InputOption::VALUE_REQUIRED, 'Source sitemap URL', 'https://www.housenutrition.tn/sitemap.xml')
            ->addOption('refresh-images', null, InputOption::VALUE_NONE, 'Redownload images for existing products');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $limit = max(1, min(200, (int) $input->getOption('limit')));
        $sitemapUrl = trim((string) $input->getOption('sitemap'));
        $refreshImages = (bool) $input->getOption('refresh-images');

        if ($sitemapUrl === '') {
            $io->error('Sitemap URL cannot be empty.');
            return Command::FAILURE;
        }

        $uploadDir = $this->projectDir . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'supplements';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
            $io->error('Could not create supplements upload directory.');
            return Command::FAILURE;
        }

        $io->text('Fetching sitemap...');
        $sitemap = $this->requestText($sitemapUrl);
        if ($sitemap === null) {
            $io->error('Failed to download sitemap: ' . $sitemapUrl);
            return Command::FAILURE;
        }

        $productUrls = $this->extractProductUrlsFromSitemap($sitemap);
        if (count($productUrls) === 0) {
            $io->error('No product URLs found in sitemap.');
            return Command::FAILURE;
        }

        $productUrls = array_slice($productUrls, 0, $limit);
        $io->text(sprintf('Found %d product URLs (processing first %d).', count($this->extractProductUrlsFromSitemap($sitemap)), count($productUrls)));

        $existingByKey = [];
        foreach ($this->supplementRepository->findAll() as $existingSupplement) {
            $key = $this->slugify((string) $existingSupplement->getName());
            if ($key !== '') {
                $existingByKey[$key] = $existingSupplement;
            }
        }

        $created = 0;
        $updated = 0;
        $failed = 0;
        $processed = 0;

        foreach ($productUrls as $index => $productUrl) {
            $processed++;
            $io->writeln(sprintf('[%d/%d] %s', $processed, count($productUrls), $productUrl));

            $html = $this->requestText($productUrl);
            if ($html === null) {
                $failed++;
                $io->warning('  - Skipped: could not download product page.');
                continue;
            }

            $product = $this->extractProductData($html, $productUrl);
            if ($product === null) {
                $failed++;
                $io->warning('  - Skipped: could not parse product data.');
                continue;
            }

            $key = $this->slugify($product['name']);
            if ($key === '') {
                $failed++;
                $io->warning('  - Skipped: invalid product name.');
                continue;
            }

            $supplement = $existingByKey[$key] ?? null;
            $isNew = false;
            if (!$supplement instanceof Supplement) {
                $supplement = new Supplement();
                $isNew = true;
                $this->entityManager->persist($supplement);
                $existingByKey[$key] = $supplement;
            }

            $supplement->setName(substr($product['name'], 0, 255));
            $supplement->setCategory(substr($product['category'], 0, 100));
            $supplement->setBrand(substr($product['brand'], 0, 100));
            $supplement->setPrice(number_format($product['price'], 2, '.', ''));
            $supplement->setDescription($product['description']);
            $supplement->setCalories(null);

            if ($isNew) {
                $supplement->setStock($this->defaultStockForName($product['name']));
            } elseif (($supplement->getStock() ?? 0) <= 0) {
                $supplement->setStock(8);
            }

            $currentImage = $supplement->getImage();
            if ($refreshImages || !$currentImage) {
                $downloadedImage = $this->downloadImage($product['imageUrl'], $product['name'], $uploadDir);
                if ($downloadedImage !== null) {
                    $supplement->setImage($downloadedImage);
                }
            }

            if ($isNew) {
                $created++;
                $io->writeln('  - Created');
            } else {
                $updated++;
                $io->writeln('  - Updated');
            }

            if ((($index + 1) % 15) === 0) {
                $this->entityManager->flush();
            }
        }

        $this->entityManager->flush();

        $io->success(sprintf(
            'Import complete. Created: %d, Updated: %d, Failed: %d',
            $created,
            $updated,
            $failed
        ));

        return Command::SUCCESS;
    }

    /**
     * @return string[]
     */
    private function extractProductUrlsFromSitemap(string $xml): array
    {
        preg_match_all('#<loc>\s*(https://www\.housenutrition\.tn/product/[^<\s]+)\s*</loc>#i', $xml, $matches);
        $urls = $matches[1] ?? [];
        $urls = array_map(static fn (string $url): string => trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8')), $urls);

        return array_values(array_unique(array_filter($urls, static fn (string $url): bool => $url !== '')));
    }

    /**
     * @return array{name: string, brand: string, category: string, price: float, description: string, imageUrl: string}|null
     */
    private function extractProductData(string $html, string $url): ?array
    {
        $schema = $this->extractProductSchema($html);

        $name = $this->cleanText((string) ($schema['name'] ?? $this->extractMeta($html, 'og:title')));
        $brand = $this->cleanText((string) ($schema['brand']['name'] ?? $schema['brand'] ?? ''));
        $category = $this->cleanText((string) ($schema['category'] ?? ''));
        $description = $this->cleanText((string) ($schema['description'] ?? $this->extractMetaByName($html, 'description')));
        $imageUrl = $this->cleanText((string) ($this->extractSchemaImage($schema) ?? $this->extractMeta($html, 'og:image')));
        $price = $this->normalizePrice((string) ($schema['offers']['price'] ?? $this->extractMeta($html, 'product:price:amount')));

        if ($name === '') {
            return null;
        }

        if ($brand === '') {
            $brand = $this->inferBrandFromName($name);
        }
        if ($brand === '') {
            $brand = 'House Nutrition';
        }

        if ($category === '') {
            $category = $this->inferCategory($name . ' ' . $description);
        }
        if ($category === '') {
            $category = 'Supplements';
        }

        if ($description === '') {
            $description = 'Imported from House Nutrition listing: ' . $url;
        }

        if ($price <= 0) {
            $price = 49.00;
        }

        if ($imageUrl !== '' && str_starts_with($imageUrl, '/')) {
            $imageUrl = 'https://www.housenutrition.tn' . $imageUrl;
        }

        return [
            'name' => $name,
            'brand' => $brand,
            'category' => $category,
            'price' => $price,
            'description' => $description,
            'imageUrl' => $imageUrl,
        ];
    }

    /**
     * @return array<mixed>
     */
    private function extractProductSchema(string $html): array
    {
        preg_match_all('#<script type="application/ld\+json">\s*(.*?)\s*</script>#is', $html, $matches);
        $blocks = $matches[1] ?? [];

        foreach ($blocks as $block) {
            $json = html_entity_decode(trim($block), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $decoded = json_decode($json, true);
            if (!is_array($decoded)) {
                continue;
            }

            $product = $this->findProductSchemaNode($decoded);
            if (is_array($product)) {
                return $product;
            }
        }

        return [];
    }

    /**
     * @param array<mixed> $node
     * @return array<mixed>|null
     */
    private function findProductSchemaNode(array $node): ?array
    {
        if (isset($node['@type'])) {
            $type = $node['@type'];
            if (
                (is_string($type) && strtolower($type) === 'product') ||
                (is_array($type) && in_array('Product', $type, true))
            ) {
                return $node;
            }
        }

        if (array_is_list($node)) {
            foreach ($node as $child) {
                if (is_array($child)) {
                    $found = $this->findProductSchemaNode($child);
                    if ($found !== null) {
                        return $found;
                    }
                }
            }
        }

        if (isset($node['@graph']) && is_array($node['@graph'])) {
            foreach ($node['@graph'] as $child) {
                if (is_array($child)) {
                    $found = $this->findProductSchemaNode($child);
                    if ($found !== null) {
                        return $found;
                    }
                }
            }
        }

        return null;
    }

    private function extractSchemaImage(array $schema): ?string
    {
        $image = $schema['image'] ?? null;
        if (is_string($image) && $image !== '') {
            return $image;
        }
        if (is_array($image) && isset($image[0]) && is_string($image[0]) && $image[0] !== '') {
            return $image[0];
        }

        return null;
    }

    private function extractMeta(string $html, string $property): string
    {
        $property = preg_quote($property, '#');
        if (preg_match('#<meta\s+property="' . $property . '"\s+content="([^"]*)"#i', $html, $match) === 1) {
            return (string) $match[1];
        }

        return '';
    }

    private function extractMetaByName(string $html, string $name): string
    {
        $name = preg_quote($name, '#');
        if (preg_match('#<meta\s+name="' . $name . '"\s+content="([^"]*)"#i', $html, $match) === 1) {
            return (string) $match[1];
        }

        return '';
    }

    private function cleanText(string $value): string
    {
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = strip_tags($value);
        $value = preg_replace('/\s+/', ' ', $value) ?? '';
        $value = trim($value);

        if (str_contains($value, 'Ã')) {
            $converted = @iconv('ISO-8859-1', 'UTF-8//IGNORE', $value);
            if (is_string($converted) && $converted !== '') {
                $value = $converted;
                $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');
            }
        }

        return $value;
    }

    private function normalizePrice(string $raw): float
    {
        $value = preg_replace('/[^0-9,.\-]/', '', $raw) ?? '';
        if ($value === '') {
            return 0.0;
        }

        if (str_contains($value, ',') && str_contains($value, '.')) {
            $value = str_replace(',', '', $value);
        } else {
            $value = str_replace(',', '.', $value);
        }

        return round((float) $value, 2);
    }

    private function inferBrandFromName(string $name): string
    {
        $chunks = preg_split('/\s*-\s*/', $name) ?: [];
        if (count($chunks) >= 2) {
            $candidate = trim((string) end($chunks));
            if ($candidate !== '' && strlen($candidate) <= 40) {
                return $candidate;
            }
        }

        return '';
    }

    private function inferCategory(string $text): string
    {
        $value = strtolower($text);

        if (str_contains($value, 'whey') || str_contains($value, 'protein') || str_contains($value, 'mass')) {
            return 'Protein';
        }
        if (str_contains($value, 'creatine')) {
            return 'Creatine';
        }
        if (str_contains($value, 'bcaa') || str_contains($value, 'eaa') || str_contains($value, 'amino')) {
            return 'Amino Acids';
        }
        if (str_contains($value, 'vitamin') || str_contains($value, 'zinc') || str_contains($value, 'magnesium') || str_contains($value, 'ashwagandha')) {
            return 'Vitamins';
        }
        if (str_contains($value, 'pre') && str_contains($value, 'workout')) {
            return 'Pre-Workout';
        }

        return 'Supplements';
    }

    private function defaultStockForName(string $name): int
    {
        return 12 + (abs(crc32($name)) % 45);
    }

    private function slugify(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        return trim($value, '-');
    }

    private function downloadImage(string $imageUrl, string $name, string $uploadDir): ?string
    {
        if ($imageUrl === '') {
            return null;
        }

        $binary = $this->requestBinary($imageUrl);
        if ($binary === null) {
            return null;
        }

        $path = parse_url($imageUrl, PHP_URL_PATH);
        $extension = strtolower(pathinfo((string) $path, PATHINFO_EXTENSION));
        if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $extension = 'jpg';
        }

        $base = substr($this->slugify($name), 0, 80);
        if ($base === '') {
            $base = 'supplement';
        }
        $filename = sprintf('house-%s-%s.%s', $base, substr(sha1($imageUrl), 0, 10), $extension);
        $target = $uploadDir . DIRECTORY_SEPARATOR . $filename;

        if (@file_put_contents($target, $binary) === false) {
            return null;
        }

        return $filename;
    }

    private function requestText(string $url): ?string
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 35);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 12);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/130.0 Safari/537.36');
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: text/html,application/xhtml+xml']);
        $result = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if (!is_string($result) || $result === '' || $status >= 400 || $error !== '') {
            return null;
        }

        return $result;
    }

    private function requestBinary(string $url): ?string
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 35);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 12);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/130.0 Safari/537.36');
        $result = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if (!is_string($result) || $result === '' || $status >= 400 || $error !== '') {
            return null;
        }

        return $result;
    }
}
