<?php

namespace App\Controller;

use App\Entity\Product;
use App\Entity\User;
use App\Form\ProductType;
use App\Repository\ProductRepository;
use App\Service\ActivityLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/product')]
#[IsGranted('ROLE_USER')]
class ProductController extends AbstractController
{
    public function __construct(
        private ActivityLogger $activityLogger
    ) {
    }

    #[Route('/', name: 'app_product_index', methods: ['GET'])]
    public function index(ProductRepository $productRepository): Response
    {
        $user = $this->getUser();
        
        // Staff can only see their own products, Admin can see all
        if ($user instanceof User && $user->isStaff() && !$user->isAdmin()) {
            $products = $productRepository->findBy(['createdBy' => $user]);
        } else {
            $products = $productRepository->findAll();
        }
        
        return $this->render('product/index.html.twig', [
            'products' => $products,
        ]);
    }

    #[Route('/new', name: 'app_product_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_STAFF')]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $product = new Product();
        $user = $this->getUser();
        
        // Set the creator
        if ($user instanceof User) {
            $product->setCreatedBy($user);
        }
        
        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($product);
            $entityManager->flush();

            // Log the creation
            if ($user instanceof User) {
                $this->activityLogger->logRecordCreated('Product', $product->getId(), $product->getName(), $user);
            }

            $admin = $request->query->get('admin');
            if ($admin === '1') {
                return $this->redirectToRoute('app_admin_products', ['admin' => 1]);
            }
            $embed = $request->query->get('embed');
            return $this->redirectToRoute('app_product_index', $embed === '1' ? ['embed' => 1] : []);
        }

        return $this->render('product/new.html.twig', [
            'product' => $product,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_product_show', methods: ['GET'])]
    public function show(int $id, ProductRepository $productRepository, Request $request): Response
    {
        $product = $productRepository->find($id);
        if (!$product) {
            $this->addFlash('warning', 'Product not found.');
            return $this->redirectToRoute('app_product_index');
        }

        $user = $this->getUser();
        
        // Staff can only view their own products
        if ($user instanceof User && $user->isStaff() && !$user->isAdmin()) {
            if ($product->getCreatedBy() !== $user) {
                throw new AccessDeniedHttpException('You can only view your own products.');
            }
        }

        // Check if this is an embedded view (modal)
        $embed = $request->query->get('embed') == '1';
        
        if ($embed) {
            // Use the embedded template for modal views
            return $this->render('product/show_embedded.html.twig', [
                'product' => $product,
            ]);
        }

        return $this->render('product/show.html.twig', [
            'product' => $product,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_product_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_STAFF')]
    public function edit(Request $request, int $id, ProductRepository $productRepository, EntityManagerInterface $entityManager): Response
    {
        $product = $productRepository->find($id);
        if (!$product) {
            $this->addFlash('warning', 'Product not found.');
            return $this->redirectToRoute('app_product_index');
        }

        $user = $this->getUser();
        
        // Staff can only edit their own products, Admin can edit any
        if ($user instanceof User && $user->isStaff() && !$user->isAdmin()) {
            if ($product->getCreatedBy() !== $user) {
                throw new AccessDeniedHttpException('You can only edit your own products.');
            }
        }

        $originalData = [
            'name' => $product->getName(),
            'price' => $product->getPrice(),
            'expiryDate' => $product->getExpiryDate(),
        ];

        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Track changes
            $changes = [];
            if ($product->getName() !== $originalData['name']) {
                $changes['name'] = ['old' => $originalData['name'], 'new' => $product->getName()];
            }
            if ($product->getPrice() !== $originalData['price']) {
                $changes['price'] = ['old' => $originalData['price'], 'new' => $product->getPrice()];
            }
            if ($product->getExpiryDate() != $originalData['expiryDate']) {
                $changes['expiryDate'] = ['old' => $originalData['expiryDate']?->format('Y-m-d'), 'new' => $product->getExpiryDate()?->format('Y-m-d')];
            }
            
            $entityManager->flush();

            // Log the update
            if ($user instanceof User) {
                $this->activityLogger->logRecordUpdated('Product', $product->getId(), $product->getName(), $changes, $user);
            }

            $admin = $request->query->get('admin');
            if ($admin === '1') {
                return $this->redirectToRoute('app_admin_products', ['admin' => 1]);
            }
            $embed = $request->query->get('embed');
            $params = ['modal' => 'close'];
            if ($embed === '1') { $params['embed'] = 1; }
            return $this->redirectToRoute('app_product_index', $params);
        }

        return $this->render('product/edit.html.twig', [
            'product' => $product,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_product_delete', methods: ['POST'])]
    #[IsGranted('ROLE_STAFF')]
    public function delete(Request $request, int $id, ProductRepository $productRepository, EntityManagerInterface $entityManager): Response
    {
        $product = $productRepository->find($id);
        if (!$product) {
            $this->addFlash('warning', 'Product not found.');
            return $this->redirectToRoute('app_product_index');
        }

        $user = $this->getUser();
        
        // Staff can only delete their own products, Admin can delete any
        if ($user instanceof User && $user->isStaff() && !$user->isAdmin()) {
            if ($product->getCreatedBy() !== $user) {
                throw new AccessDeniedHttpException('You can only delete your own products.');
            }
        }

        if ($this->isCsrfTokenValid('delete' . $product->getId(), $request->request->get('_token'))) {
            $productName = $product->getName();
            $productId = $product->getId();
            
            $entityManager->remove($product);
            $entityManager->flush();

            // Log the deletion
            if ($user instanceof User) {
                $this->activityLogger->logRecordDeleted('Product', $productId, $productName, $user);
            }
            
            $this->addFlash('success', 'Product deleted successfully.');
        }

        $admin = $request->query->get('admin');
        if ($admin === '1') {
            return $this->redirectToRoute('app_admin_products', ['admin' => 1]);
        }
        $embed = $request->query->get('embed');
        return $this->redirectToRoute('app_product_index', $embed === '1' ? ['embed' => 1] : []);
    }
}
