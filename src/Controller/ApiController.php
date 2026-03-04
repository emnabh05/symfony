<?php

namespace App\Controller;

use App\Entity\Supplement;
use App\Repository\SupplementRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
class ApiController extends AbstractController
{
    public function __construct(
        private SupplementRepository $supplementRepository,
    ) {
    }

    #[Route('/supplements', name: 'api_supplements', methods: ['GET'])]
    public function getSupplements(Request $request): JsonResponse
    {
        // Get all supplements
        $supplements = $this->supplementRepository->findCatalogLimited();
        
        // Get filter parameters
        $category = $request->query->get('category');
        $brand = $request->query->get('brand');
        $minPrice = $request->query->get('minPrice');
        $maxPrice = $request->query->get('maxPrice');
        
        // Filter supplements
        $filteredSupplements = array_filter($supplements, function(Supplement $supplement) use ($category, $brand, $minPrice, $maxPrice) {
            if ($category && $supplement->getCategory() !== $category) {
                return false;
            }
            if ($brand && $supplement->getBrand() !== $brand) {
                return false;
            }
            if ($minPrice && $supplement->getPrice() < $minPrice) {
                return false;
            }
            if ($maxPrice && $supplement->getPrice() > $maxPrice) {
                return false;
            }
            return true;
        });
        
        // Convert to array
        $data = array_map(function(Supplement $supplement) {
            return [
                'id' => $supplement->getId(),
                'name' => $supplement->getName(),
                'category' => $supplement->getCategory(),
                'brand' => $supplement->getBrand(),
                'price' => (float) $supplement->getPrice(),
                'stock' => $supplement->getStock(),
                'calories' => $supplement->getCalories(),
                'description' => $supplement->getDescription(),
                'image' => $supplement->getImage() ? 'http://localhost:8000/uploads/supplements/' . $supplement->getImage() : null,
                'createdAt' => $supplement->getCreatedAt()?->format('Y-m-d H:i:s'),
                'isNew' => $supplement->getCreatedAt() && $supplement->getCreatedAt() > new \DateTime('-30 days'),
            ];
        }, array_values($filteredSupplements));

        // Add CORS headers
        $response = new JsonResponse($data);
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type');
        
        return $response;
    }

    #[Route('/supplement/{id}', name: 'api_supplement_show', methods: ['GET'])]
    public function getSupplementById(Supplement $supplement): JsonResponse
    {
        $data = [
            'id' => $supplement->getId(),
            'name' => $supplement->getName(),
            'category' => $supplement->getCategory(),
            'brand' => $supplement->getBrand(),
            'price' => (float) $supplement->getPrice(),
            'stock' => $supplement->getStock(),
            'calories' => $supplement->getCalories(),
            'description' => $supplement->getDescription(),
            'image' => $supplement->getImage() ? 'http://localhost:8000/uploads/supplements/' . $supplement->getImage() : null,
            'createdAt' => $supplement->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updatedAt' => $supplement->getUpdatedAt()?->format('Y-m-d H:i:s'),
        ];

        $response = new JsonResponse($data);
        $response->headers->set('Access-Control-Allow-Origin', '*');
        
        return $response;
    }

    #[Route('/categories', name: 'api_categories', methods: ['GET'])]
    public function getCategories(): JsonResponse
    {
        $supplements = $this->supplementRepository->findCatalogLimited();
        
        $categories = [];
        foreach ($supplements as $supplement) {
            $category = $supplement->getCategory();
            if ($category && !isset($categories[$category])) {
                $categories[$category] = 0;
            }
            if ($category) {
                $categories[$category]++;
            }
        }
        
        $data = array_map(function($name, $count) {
            return ['name' => $name, 'count' => $count];
        }, array_keys($categories), array_values($categories));

        $response = new JsonResponse($data);
        $response->headers->set('Access-Control-Allow-Origin', '*');
        
        return $response;
    }

    #[Route('/brands', name: 'api_brands', methods: ['GET'])]
    public function getBrands(): JsonResponse
    {
        $supplements = $this->supplementRepository->findCatalogLimited();
        
        $brands = [];
        foreach ($supplements as $supplement) {
            $brand = $supplement->getBrand();
            if ($brand && !isset($brands[$brand])) {
                $brands[$brand] = 0;
            }
            if ($brand) {
                $brands[$brand]++;
            }
        }
        
        $data = array_map(function($name, $count) {
            return ['name' => $name, 'count' => $count];
        }, array_keys($brands), array_values($brands));

        $response = new JsonResponse($data);
        $response->headers->set('Access-Control-Allow-Origin', '*');
        
        return $response;
    }
}

