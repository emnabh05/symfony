<?php

namespace App\Controller;

use App\Entity\Supplement;
use App\Entity\Review;
use App\Form\SupplementType;
use App\Repository\OrderItemRepository;
use App\Repository\SupplementRepository;
use App\Service\FileUploader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/supplements')]
class SupplementController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SupplementRepository $supplementRepository,
        private OrderItemRepository $orderItemRepository,
        private FileUploader $fileUploader,
    ) {
    }

    #[Route('', name: 'app_supplement_index', methods: ['GET', 'POST'])]
    public function index(Request $request, SessionInterface $session): Response
    {
        $mode = (string) $request->query->get('mode', 'shop');
        $id = $request->query->getInt('id', 0);

        $supplements = $this->supplementRepository->findCatalogLimited();
        $supplementIds = array_map(static fn($supplement) => $supplement->getId(), $supplements);
        $reviewRepository = $this->entityManager->getRepository(Review::class);
        $ratingSummary = method_exists($reviewRepository, 'getSummaryForSupplements')
            ? $reviewRepository->getSummaryForSupplements($supplementIds)
            : [];

        $categories = array_unique(array_map(fn($s) => $s->getCategory(), $supplements));
        $brands = array_unique(array_map(fn($s) => $s->getBrand(), $supplements));
        sort($categories);
        sort($brands);

        if (!$session->isStarted()) {
            $session->start();
        }

        $cart = $session->get('cart', []);
        $cartItems = [];
        $subtotal = 0;

        foreach ($cart as $item) {
            $supplement = $this->supplementRepository->find($item['id']);
            if ($supplement) {
                $itemTotal = $supplement->getPrice() * $item['quantity'];
                $cartItems[] = [
                    'supplement' => $supplement,
                    'quantity' => $item['quantity'],
                    'total' => $itemTotal,
                ];
                $subtotal += $itemTotal;
            }
        }

        $paymentMethods = [
            ['id' => 'visa', 'name' => 'Visa', 'icon' => 'fab fa-cc-visa'],
            ['id' => 'mastercard', 'name' => 'Mastercard', 'icon' => 'fab fa-cc-mastercard'],
            ['id' => 'paypal', 'name' => 'PayPal', 'icon' => 'fab fa-cc-paypal'],
            ['id' => 'cod', 'name' => 'Cash on Delivery', 'icon' => 'fas fa-money-bill-wave'],
        ];
        $viewData = [
            'mode' => $mode,
            'supplements' => $supplements,
            'categories' => $categories,
            'brands' => $brands,
            'ratingSummary' => $ratingSummary,
            'cartItems' => $cartItems,
            'subtotal' => $subtotal,
            'paymentMethods' => $paymentMethods,
        ];

        $newSupplement = new Supplement();
        $newForm = $this->createForm(SupplementType::class, $newSupplement);

        if ('new' === $mode) {
            $newForm->handleRequest($request);

            if ($newForm->isSubmitted() && $newForm->isValid()) {
                /** @var UploadedFile|null $imageFile */
                $imageFile = $newForm->get('imageFile')->getData();

                if ($imageFile) {
                    $imageFileName = $this->fileUploader->upload($imageFile);
                    $newSupplement->setImage($imageFileName);
                }

                $this->entityManager->persist($newSupplement);
                $this->entityManager->flush();
                $this->addFlash('success', 'Supplement created successfully!');

                return $this->redirectToRoute('app_supplement_index');
            }
        }

        $viewData['newForm'] = $newForm->createView();

        $editForm = $this->createForm(SupplementType::class, new Supplement());

        if ('edit' === $mode && $id > 0) {
            $supplement = $this->supplementRepository->find($id);
            if (!$supplement) {
                throw $this->createNotFoundException('Supplement not found.');
            }

            $editForm = $this->createForm(SupplementType::class, $supplement);
            $editForm->handleRequest($request);

            if ($editForm->isSubmitted() && $editForm->isValid()) {
                /** @var UploadedFile|null $imageFile */
                $imageFile = $editForm->get('imageFile')->getData();

                if ($imageFile) {
                    if ($supplement->getImage()) {
                        $oldImagePath = $this->fileUploader->getTargetDirectory() . '/' . $supplement->getImage();
                        if (file_exists($oldImagePath)) {
                            unlink($oldImagePath);
                        }
                    }

                    $imageFileName = $this->fileUploader->upload($imageFile);
                    $supplement->setImage($imageFileName);
                }

                $this->entityManager->flush();
                $this->addFlash('success', 'Supplement updated successfully!');

                return $this->redirectToRoute('app_supplement_index');
            }

            $viewData['editingSupplement'] = $supplement;
        }

        $viewData['editForm'] = $editForm->createView();

        if ('show' === $mode && $id > 0) {
            $supplement = $this->supplementRepository->find($id);
            if (!$supplement) {
                throw $this->createNotFoundException('Supplement not found.');
            }

            $viewData['selectedSupplement'] = $supplement;
        }

        return $this->render('pages/supplements.html.twig', $viewData);
    }

    #[Route('/new', name: 'app_supplement_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $supplement = new Supplement();
        $form = $this->createForm(SupplementType::class, $supplement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile|null $imageFile */
            $imageFile = $form->get('imageFile')->getData();

            if ($imageFile) {
                $imageFileName = $this->fileUploader->upload($imageFile);
                $supplement->setImage($imageFileName);
            }

            $this->entityManager->persist($supplement);
            $this->entityManager->flush();
            $this->addFlash('success', 'Supplement created successfully!');

            return $this->redirectToRoute('app_supplement_index');
        }

        return $this->render('supplement/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_supplement_show', methods: ['GET'])]
    public function show(Supplement $supplement): Response
    {
        return $this->redirectToRoute('app_supplement_index', [
            'mode' => 'show',
            'id' => $supplement->getId(),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_supplement_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Supplement $supplement): Response
    {
        $form = $this->createForm(SupplementType::class, $supplement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile|null $imageFile */
            $imageFile = $form->get('imageFile')->getData();

            if ($imageFile) {
                if ($supplement->getImage()) {
                    $oldImagePath = $this->fileUploader->getTargetDirectory() . '/' . $supplement->getImage();
                    if (file_exists($oldImagePath)) {
                        unlink($oldImagePath);
                    }
                }

                $imageFileName = $this->fileUploader->upload($imageFile);
                $supplement->setImage($imageFileName);
            }

            $this->entityManager->flush();
            $this->addFlash('success', 'Supplement updated successfully!');

            return $this->redirectToRoute('app_supplement_index');
        }

        return $this->render('supplement/edit.html.twig', [
            'supplement' => $supplement,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/delete', name: 'app_supplement_delete', methods: ['POST'])]
    public function delete(Request $request, Supplement $supplement): Response
    {
        if ($this->isCsrfTokenValid('delete'.$supplement->getId(), (string) $request->request->get('_token'))) {
            $orderItemCount = $this->orderItemRepository->count(['supplement' => $supplement]);
            if ($orderItemCount > 0) {
                $this->addFlash('error', 'Cannot delete this supplement because it is linked to existing orders.');
                return $this->redirectToRoute('app_supplement_index');
            }

            // Delete image file if exists
            if ($supplement->getImage()) {
                $imagePath = $this->fileUploader->getTargetDirectory() . '/' . $supplement->getImage();
                if (file_exists($imagePath)) {
                    unlink($imagePath);
                }
            }

            $this->entityManager->remove($supplement);
            $this->entityManager->flush();

            $this->addFlash('success', 'Supplement deleted successfully!');
        }

        return $this->redirectToRoute('app_supplement_index');
    }

}
