<?php

namespace App\Controller\Staff;

use App\Entity\Product;
use App\Entity\User;
use App\Form\ProductType;
use App\Repository\ProductRepository;
use App\Service\ActivityLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/staff/products')]
#[IsGranted('ROLE_STAFF')]
class ProductController extends AbstractController
{
    public function __construct(
        private ProductRepository $productRepository,
        private EntityManagerInterface $entityManager,
        private ActivityLogger $activityLogger,
        private SluggerInterface $slugger
    ) {
    }

    #[Route('', name: 'staff_product_index', methods: ['GET'])]
    public function index(): Response
    {
        // Staff can see all products including admin-created ones
        $products = $this->productRepository->findAll();
        
        return $this->render('staff/product/index.html.twig', [
            'products' => $products,
            'page_title' => 'All Products',
        ]);
    }

    #[Route('/new', name: 'staff_product_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
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
            /** @var UploadedFile $imageFile */
            $imageFile = $form->get('imageFile')->getData();
            
            if ($imageFile) {
                // Validate file extension
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                $originalExtension = strtolower($imageFile->getClientOriginalExtension());
                
                if (!in_array($originalExtension, $allowedExtensions)) {
                    $this->addFlash('error', 'Invalid file type. Please upload a JPG, PNG, GIF, or WebP image.');
                } else {
                    $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                    $safeFilename = $this->slugger->slug($originalFilename);
                    
                    // Use original extension since fileinfo might not be available
                    $newFilename = $safeFilename . '-' . uniqid() . '.' . $originalExtension;
                    
                    try {
                        $imageFile->move(
                            $this->getParameter('kernel.project_dir') . '/public/uploads/products',
                            $newFilename
                        );
                        $product->setImage('uploads/products/' . $newFilename);
                    } catch (FileException $e) {
                        $this->addFlash('error', 'Failed to upload image: ' . $e->getMessage());
                    }
                }
            }
            
            $this->entityManager->persist($product);
            $this->entityManager->flush();

            // Log the creation
            if ($user instanceof User) {
                $this->activityLogger->logRecordCreated('Product', $product->getId(), $product->getName(), $user);
            }

            $this->addFlash('success', 'Product created successfully.');
            return $this->redirectToRoute('staff_product_index');
        }

        return $this->render('staff/product/new.html.twig', [
            'product' => $product,
            'form' => $form->createView(),
            'page_title' => 'Create New Product',
        ]);
    }

    #[Route('/{id}', name: 'staff_product_show', methods: ['GET'])]
    public function show(int $id): Response
    {
        $product = $this->productRepository->find($id);
        if (!$product) {
            $this->addFlash('warning', 'Product not found.');
            return $this->redirectToRoute('staff_product_index');
        }

        // Staff can view all products including admin-created ones
        return $this->render('staff/product/show.html.twig', [
            'product' => $product,
            'page_title' => 'Product Details',
        ]);
    }

    #[Route('/{id}/edit', name: 'staff_product_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, int $id): Response
    {
        $product = $this->productRepository->find($id);
        if (!$product) {
            $this->addFlash('warning', 'Product not found.');
            return $this->redirectToRoute('staff_product_index');
        }

        $user = $this->getUser();
        
        // Staff can edit all products including admin-created ones

        $originalData = [
            'name' => $product->getName(),
            'price' => $product->getPrice(),
            'expiryDate' => $product->getExpiryDate(),
        ];

        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile $imageFile */
            $imageFile = $form->get('imageFile')->getData();
            
            if ($imageFile) {
                // Validate file extension
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                $originalExtension = strtolower($imageFile->getClientOriginalExtension());
                
                if (!in_array($originalExtension, $allowedExtensions)) {
                    $this->addFlash('error', 'Invalid file type. Please upload a JPG, PNG, GIF, or WebP image.');
                } else {
                    // Delete old image if exists
                    if ($product->getImage()) {
                        $oldImagePath = $this->getParameter('kernel.project_dir') . '/public/' . $product->getImage();
                        if (file_exists($oldImagePath)) {
                            unlink($oldImagePath);
                        }
                    }
                    
                    $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                    $safeFilename = $this->slugger->slug($originalFilename);
                    
                    // Use original extension since fileinfo might not be available
                    $newFilename = $safeFilename . '-' . uniqid() . '.' . $originalExtension;
                    
                    try {
                        $imageFile->move(
                            $this->getParameter('kernel.project_dir') . '/public/uploads/products',
                            $newFilename
                        );
                        $product->setImage('uploads/products/' . $newFilename);
                    } catch (FileException $e) {
                        $this->addFlash('error', 'Failed to upload image: ' . $e->getMessage());
                    }
                }
            }
            
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
            
            $this->entityManager->flush();

            // Log the update
            if ($user instanceof User && !empty($changes)) {
                $this->activityLogger->logRecordUpdated('Product', $product->getId(), $product->getName(), $changes, $user);
            }

            $this->addFlash('success', 'Product updated successfully.');
            return $this->redirectToRoute('staff_product_index');
        }

        return $this->render('staff/product/edit.html.twig', [
            'product' => $product,
            'form' => $form->createView(),
            'page_title' => 'Edit Product',
        ]);
    }

    #[Route('/{id}', name: 'staff_product_delete', methods: ['POST'])]
    public function delete(Request $request, int $id): Response
    {
        $product = $this->productRepository->find($id);
        if (!$product) {
            $this->addFlash('warning', 'Product not found.');
            return $this->redirectToRoute('staff_product_index');
        }

        $user = $this->getUser();
        
        // Staff can delete products created by other staff, but not admin-created products
        if ($user instanceof User && $user->isStaff() && !$user->isAdmin()) {
            $createdBy = $product->getCreatedBy();
            // Check if product was created by an admin
            if ($createdBy && $createdBy->isAdmin()) {
                $this->addFlash('error', 'Only administrators can delete products created by admins.');
                return $this->redirectToRoute('staff_product_index');
            }
            // Staff can delete products created by other staff or themselves
        }

        if ($this->isCsrfTokenValid('delete' . $product->getId(), $request->request->get('_token'))) {
            $productName = $product->getName();
            $productId = $product->getId();
            
            $this->entityManager->remove($product);
            $this->entityManager->flush();

            // Log the deletion
            if ($user instanceof User) {
                $this->activityLogger->logRecordDeleted('Product', $productId, $productName, $user);
            }
            
            $this->addFlash('success', 'Product deleted successfully.');
        }

        return $this->redirectToRoute('staff_product_index');
    }
}

