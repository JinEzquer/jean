<?php

namespace App\Controller\Staff;

use App\Entity\Category;
use App\Entity\User;
use App\Form\CategoryType;
use App\Repository\CategoryRepository;
use App\Service\ActivityLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/staff/categories')]
#[IsGranted('ROLE_STAFF')]
class CategoryController extends AbstractController
{
    public function __construct(
        private CategoryRepository $categoryRepository,
        private EntityManagerInterface $entityManager,
        private ActivityLogger $activityLogger
    ) {
    }

    #[Route('', name: 'staff_category_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('staff/category/index.html.twig', [
            'page_title' => 'Categories',
            'categories' => $this->categoryRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'staff_category_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $category = new Category();
        $user = $this->getUser();
        
        $categoryForm = $this->createForm(CategoryType::class, $category);
        $categoryForm->handleRequest($request);

        if ($categoryForm->isSubmitted() && $categoryForm->isValid()) {
            $this->entityManager->persist($category);
            $this->entityManager->flush();

            // Log the creation
            if ($user instanceof User) {
                $this->activityLogger->logRecordCreated('Category', $category->getId(), $category->getName(), $user);
            }

            $this->addFlash('success', 'Category created successfully.');
            return $this->redirectToRoute('staff_category_index');
        }

        return $this->render('staff/category/new.html.twig', [
            'page_title' => 'Create New Category',
            'categoryForm' => $categoryForm->createView(),
        ]);
    }

    #[Route('/{id}', name: 'staff_category_show', methods: ['GET'])]
    public function show(Category $category): Response
    {
        return $this->render('staff/category/show.html.twig', [
            'page_title' => 'Category Details',
            'category' => $category,
        ]);
    }

    #[Route('/{id}/edit', name: 'staff_category_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Category $category): Response
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

            $this->entityManager->flush();

            // Log the update
            if ($user instanceof User && !empty($changes)) {
                $this->activityLogger->logRecordUpdated('Category', $category->getId(), $category->getName(), $changes, $user);
            }

            $this->addFlash('success', 'Category updated successfully.');
            return $this->redirectToRoute('staff_category_index');
        }

        return $this->render('staff/category/edit.html.twig', [
            'page_title' => 'Edit Category',
            'category' => $category,
            'categoryForm' => $categoryForm->createView(),
        ]);
    }

    #[Route('/{id}', name: 'staff_category_delete', methods: ['POST'])]
    public function delete(Request $request, Category $category): Response
    {
        $user = $this->getUser();
        
        if ($this->isCsrfTokenValid('delete'.$category->getId(), $request->request->get('_token'))) {
            $categoryName = $category->getName();
            $categoryId = $category->getId();
            
            $this->entityManager->remove($category);
            $this->entityManager->flush();

            // Log the deletion
            if ($user instanceof User) {
                $this->activityLogger->logRecordDeleted('Category', $categoryId, $categoryName, $user);
            }

            $this->addFlash('success', 'Category deleted successfully.');
        }

        return $this->redirectToRoute('staff_category_index');
    }
}

