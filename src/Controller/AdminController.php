<?php

namespace App\Controller;

use App\Entity\Category;
use App\Entity\Product;
use App\Entity\User;
use App\Form\CategoryType;
use App\Form\ProductType;
use App\Repository\ProductRepository;
use App\Repository\CategoryRepository;
use App\Repository\UserRepository;
use App\Repository\ActivityLogRepository;
use App\Service\ActivityLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[IsGranted('ROLE_ADMIN')]
final class AdminController extends AbstractController
{
    private UserRepository $userRepository;
    private ProductRepository $productRepository;
    private CategoryRepository $categoryRepository;
    private ActivityLogRepository $activityLogRepository;

    public function __construct(
        UserRepository $userRepository,
        ProductRepository $productRepository,
        CategoryRepository $categoryRepository,
        ActivityLogRepository $activityLogRepository
    ) {
        $this->userRepository = $userRepository;
        $this->productRepository = $productRepository;
        $this->categoryRepository = $categoryRepository;
        $this->activityLogRepository = $activityLogRepository;
    }

    #[Route('/admin', name: 'app_admin')]
    public function index(): Response
    {
        // Get user statistics
        $totalUsers = $this->userRepository->count([]);
        $activeUsers = $this->userRepository->count(['isActive' => true]);
        
        // Count admin users using LIKE for role search
        $adminUsers = $this->userRepository->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.roles LIKE :adminRole')
            ->setParameter('adminRole', '%"ROLE_ADMIN"%')
            ->getQuery()
            ->getSingleScalarResult();
            
        // Count staff users using LIKE for role search
        $staffUsers = $this->userRepository->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.roles LIKE :staffRole')
            ->setParameter('staffRole', '%"ROLE_STAFF"%')
            ->getQuery()
            ->getSingleScalarResult();
            
        // Calculate regular users by subtracting admin and staff from total
        $regularUsers = $totalUsers - $adminUsers - $staffUsers;

        // Get product and category counts
        $totalProducts = $this->productRepository->count([]);
        $totalCategories = $this->categoryRepository->count([]);

        // Get recent activities (handle case when table doesn't exist)
        $recentActivities = [];
        try {
            $recentActivities = $this->activityLogRepository->findBy(
                [],
                ['createdAt' => 'DESC'],
                10
            );
        } catch (\Exception $e) {
            // Table doesn't exist or other error - log it and continue
            error_log('Could not fetch recent activities: ' . $e->getMessage());
        }

        // Get user registration stats for the last 30 days
        $registrationStats = [];
        try {
            $registrationStats = $this->getRegistrationStats();
        } catch (\Exception $e) {
            // Log error and continue with empty stats
            error_log('Could not fetch registration stats: ' . $e->getMessage());
        }

        // Get all products for the dashboard
        $products = $this->productRepository->findAll();

        return $this->render('admin/dashboard.html.twig', [
            'page_title' => 'Admin Dashboard',
            'products' => $products,
            'stats' => [
                'users' => [
                    'total' => $totalUsers,
                    'active' => $activeUsers,
                    'admins' => $adminUsers,
                    'staff' => $staffUsers,
                    'regular' => $regularUsers,
                ],
                'products' => $totalProducts,
                'categories' => $totalCategories,
            ],
            'recent_activities' => $recentActivities,
            'registration_stats' => json_encode($registrationStats),
        ]);
    }

    /**
     * Get user registration statistics for the last 30 days
     */
    private function getRegistrationStats(): array
    {
        $endDate = new \DateTimeImmutable();
        $startDate = $endDate->modify('-30 days');
        
        $stats = [];
        $currentDate = clone $startDate;
        
        // Initialize all dates with 0
        while ($currentDate <= $endDate) {
            $stats[$currentDate->format('Y-m-d')] = 0;
            $currentDate = $currentDate->modify('+1 day');
        }
        
        // Get actual registrations
        $registrations = $this->userRepository->createQueryBuilder('u')
            ->select('DATE(u.createdAt) as date, COUNT(u.id) as count')
            ->where('u.createdAt >= :startDate')
            ->andWhere('u.createdAt <= :endDate')
            ->setParameter('startDate', $startDate->format('Y-m-d 00:00:00'))
            ->setParameter('endDate', $endDate->format('Y-m-d 23:59:59'))
            ->groupBy('date')
            ->getQuery()
            ->getResult();
        
        // Update stats with actual values
        foreach ($registrations as $registration) {
            $stats[$registration['date']] = (int) $registration['count'];
        }
        
        // Format for chart
        $result = [
            'labels' => [],
            'data' => []
        ];
        
        foreach ($stats as $date => $count) {
            $result['labels'][] = (new \DateTimeImmutable($date))->format('M j');
            $result['data'][] = $count;
        }
        
        return $result;
    }

    #[Route('/admin/products', name: 'app_admin_products', methods: ['GET'])]
    public function products(): Response
    {
        $product = new Product();
        $user = $this->getUser();
        if ($user instanceof User) {
            $product->setCreatedBy($user);
        }
        $productForm = $this->createForm(ProductType::class, $product);

        return $this->render('admin/product/index.html.twig', [
            'page_title' => 'Products',
            'products' => $this->productRepository->findAll(),
            'productForm' => $productForm->createView(),
        ]);
    }

    #[Route('/admin/products/new', name: 'app_admin_product_new', methods: ['GET', 'POST'])]
    public function newProduct(Request $request, EntityManagerInterface $entityManager, ActivityLogger $activityLogger, SluggerInterface $slugger): Response
    {
        $product = new Product();
        $user = $this->getUser();
        
        // Set the creator
        if ($user instanceof User) {
            $product->setCreatedBy($user);
        }
        
        $productForm = $this->createForm(ProductType::class, $product);
        $productForm->handleRequest($request);

        if ($productForm->isSubmitted() && $productForm->isValid()) {
            /** @var UploadedFile $imageFile */
            $imageFile = $productForm->get('imageFile')->getData();
            
            if ($imageFile) {
                // Validate file extension
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                $originalExtension = strtolower($imageFile->getClientOriginalExtension());
                
                if (!in_array($originalExtension, $allowedExtensions)) {
                    $this->addFlash('error', 'Invalid file type. Please upload a JPG, PNG, GIF, or WebP image.');
                } else {
                    $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                    $safeFilename = $slugger->slug($originalFilename);
                    
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
            
            $entityManager->persist($product);
            $entityManager->flush();

            // Log the creation
            if ($user instanceof User) {
                $activityLogger->logRecordCreated('Product', $product->getId(), $product->getName(), $user);
            }

            $this->addFlash('success', 'Product created successfully.');

            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['success' => true]);
            }
            return $this->redirectToRoute('app_admin_products');
        }

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse(['success' => false, 'errors' => (string) $productForm->getErrors(true)], 400);
        }

        return $this->render('admin/product/new.html.twig', [
            'page_title' => 'Create New Product',
            'productForm' => $productForm->createView(),
        ]);
    }

    #[Route('/admin/categories', name: 'app_admin_categories', methods: ['GET'])]
    public function categories(): Response
    {
        return $this->render('admin/category/index.html.twig', [
            'page_title' => 'Categories',
            'categories' => $this->categoryRepository->findAll(),
        ]);
    }

    #[Route('/admin/categories/new', name: 'app_admin_category_new', methods: ['GET', 'POST'])]
    public function newCategory(Request $request, EntityManagerInterface $entityManager, ActivityLogger $activityLogger): Response
    {
        $category = new Category();
        $user = $this->getUser();
        
        $categoryForm = $this->createForm(CategoryType::class, $category);
        $categoryForm->handleRequest($request);

        if ($categoryForm->isSubmitted() && $categoryForm->isValid()) {
            $entityManager->persist($category);
            $entityManager->flush();

            // Log the creation
            if ($user instanceof User) {
                $activityLogger->logRecordCreated('Category', $category->getId(), $category->getName(), $user);
            }

            $this->addFlash('success', 'Category created successfully.');
            return $this->redirectToRoute('app_admin_categories');
        }

        return $this->render('admin/category/new.html.twig', [
            'page_title' => 'Create New Category',
            'categoryForm' => $categoryForm->createView(),
        ]);
    }

    #[Route('/admin/products/{id}/edit', name: 'app_admin_product_edit', methods: ['GET', 'POST'])]
    public function editProduct(Request $request, int $id, EntityManagerInterface $entityManager, ActivityLogger $activityLogger, SluggerInterface $slugger): Response
    {
        $product = $this->productRepository->find($id);
        if (!$product) {
            $this->addFlash('warning', 'Product not found.');
            return $this->redirectToRoute('app_admin_products');
        }

        $user = $this->getUser();
        $originalData = [
            'name' => $product->getName(),
            'price' => $product->getPrice(),
            'expiryDate' => $product->getExpiryDate(),
        ];

        $productForm = $this->createForm(ProductType::class, $product);
        $productForm->handleRequest($request);

        if ($productForm->isSubmitted() && $productForm->isValid()) {
            /** @var UploadedFile $imageFile */
            $imageFile = $productForm->get('imageFile')->getData();
            
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
                    $safeFilename = $slugger->slug($originalFilename);
                    
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
            
            $entityManager->flush();

            // Log the update
            if ($user instanceof User && !empty($changes)) {
                $activityLogger->logRecordUpdated('Product', $product->getId(), $product->getName(), $changes, $user);
            }

            $this->addFlash('success', 'Product updated successfully.');

            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['success' => true]);
            }
            return $this->redirectToRoute('app_admin_products');
        }

        if ($productForm->isSubmitted() && $request->isXmlHttpRequest()) {
            return new JsonResponse(['success' => false, 'errors' => (string) $productForm->getErrors(true)], 400);
        }

        if ($request->isXmlHttpRequest()) {
            return $this->render('admin/product/_edit_modal.html.twig', [
                'product' => $product,
                'productForm' => $productForm->createView(),
            ]);
        }

        return $this->render('admin/product/edit.html.twig', [
            'page_title' => 'Edit Product',
            'product' => $product,
            'productForm' => $productForm->createView(),
        ]);
    }

    #[Route('/admin/categories/{id}/edit', name: 'app_admin_category_edit', methods: ['GET', 'POST'])]
    public function editCategory(Request $request, Category $category, EntityManagerInterface $entityManager, ActivityLogger $activityLogger): Response
    {
        $user = $this->getUser();
        $originalData = [
            'name' => $category->getName(),
            'description' => $category->getDescription(),
        ];

        $categoryForm = $this->createForm(CategoryType::class, $category);
        $categoryForm->handleRequest($request);

        if ($categoryForm->isSubmitted() && $categoryForm->isValid()) {
            // Track changes
            $changes = [];
            if ($category->getName() !== $originalData['name']) {
                $changes['name'] = ['old' => $originalData['name'], 'new' => $category->getName()];
            }
            if ($category->getDescription() !== $originalData['description']) {
                $changes['description'] = ['old' => $originalData['description'], 'new' => $category->getDescription()];
            }

            $entityManager->flush();

            // Log the update
            if ($user instanceof User && !empty($changes)) {
                $activityLogger->logRecordUpdated('Category', $category->getId(), $category->getName(), $changes, $user);
            }

            $this->addFlash('success', 'Category updated successfully.');
            return $this->redirectToRoute('app_admin_categories');
        }

        return $this->render('admin/category/edit.html.twig', [
            'page_title' => 'Edit Category',
            'category' => $category,
            'categoryForm' => $categoryForm->createView(),
        ]);
    }

    #[Route('/admin/products/{id}', name: 'app_admin_product_show', methods: ['GET'])]
    public function showProduct(Request $request, int $id): Response
    {
        $product = $this->productRepository->find($id);
        if (!$product) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['error' => 'Product not found.'], 404);
            }
            $this->addFlash('warning', 'Product not found.');
            return $this->redirectToRoute('app_admin_products');
        }

        if ($request->isXmlHttpRequest()) {
            return $this->render('admin/product/_show_modal.html.twig', ['product' => $product]);
        }

        return $this->render('admin/product/show.html.twig', [
            'page_title' => 'Product Details',
            'product' => $product,
        ]);
    }

    #[Route('/admin/categories/{id}', name: 'app_admin_category_show', methods: ['GET'])]
    public function showCategory(Category $category): Response
    {
        return $this->render('admin/category/show.html.twig', [
            'page_title' => 'Category Details',
            'category' => $category,
        ]);
    }

    #[Route('/admin/categories/{id}/delete', name: 'app_admin_category_delete', methods: ['POST'])]
    public function deleteCategory(Request $request, Category $category, EntityManagerInterface $entityManager, ActivityLogger $activityLogger): Response
    {
        $user = $this->getUser();

        if ($this->isCsrfTokenValid('delete' . $category->getId(), $request->request->get('_token'))) {
            $categoryName = $category->getName();
            $categoryId = $category->getId();

            $entityManager->remove($category);
            $entityManager->flush();

            if ($user instanceof User) {
                $activityLogger->logRecordDeleted('Category', $categoryId, $categoryName, $user);
            }

            $this->addFlash('success', 'Category deleted successfully.');
        }

        return $this->redirectToRoute('app_admin_categories');
    }
}


