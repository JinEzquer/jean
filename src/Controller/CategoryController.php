<?php

namespace App\Controller;

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

#[Route('/category')]
final class CategoryController extends AbstractController
{
    public function __construct(
        private ActivityLogger $activityLogger
    ) {
    }
    #[Route(name: 'app_category_index', methods: ['GET'])]
    public function index(CategoryRepository $categoryRepository): Response
    {
        return $this->render('category/index.html.twig', [
            'categories' => $categoryRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_category_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_STAFF')]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $category = new Category();
        $form = $this->createForm(CategoryType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($category);
            $entityManager->flush();

            // Log the creation
            $user = $this->getUser();
            if ($user instanceof User) {
                $this->activityLogger->logRecordCreated('Category', $category->getId(), $category->getName(), $user);
            }

            $admin = $request->query->get('admin');
            if ($admin === '1') {
                return $this->redirectToRoute('app_admin_categories', ['admin' => 1], Response::HTTP_SEE_OTHER);
            }
            $embed = $request->query->get('embed');
            return $this->redirectToRoute('app_category_index', $embed === '1' ? ['embed' => 1] : [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('category/new.html.twig', [
            'category' => $category,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_category_show', methods: ['GET'])]
    public function show(Category $category): Response
    {
        return $this->render('category/show.html.twig', [
            'category' => $category,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_category_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_STAFF')]
    public function edit(Request $request, Category $category, EntityManagerInterface $entityManager): Response
    {
        $originalData = [
            'name' => $category->getName(),
            'description' => $category->getDescription(),
        ];

        $form = $this->createForm(CategoryType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
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
            $user = $this->getUser();
            if ($user instanceof User && !empty($changes)) {
                $this->activityLogger->logRecordUpdated('Category', $category->getId(), $category->getName(), $changes, $user);
            }

            $admin = $request->query->get('admin');
            if ($admin === '1') {
                return $this->redirectToRoute('app_admin_categories', ['admin' => 1], Response::HTTP_SEE_OTHER);
            }
            $embed = $request->query->get('embed');
            return $this->redirectToRoute('app_category_index', $embed === '1' ? ['embed' => 1] : [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('category/edit.html.twig', [
            'category' => $category,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_category_delete', methods: ['POST'])]
    #[IsGranted('ROLE_STAFF')]
    public function delete(Request $request, Category $category, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$category->getId(), $request->request->get('_token'))) {
            $categoryName = $category->getName();
            $categoryId = $category->getId();
            
            $entityManager->remove($category);
            $entityManager->flush();

            // Log the deletion
            $user = $this->getUser();
            if ($user instanceof User) {
                $this->activityLogger->logRecordDeleted('Category', $categoryId, $categoryName, $user);
            }
        }

        $admin = $request->query->get('admin');
        if ($admin === '1') {
            return $this->redirectToRoute('app_admin_categories', ['admin' => 1], Response::HTTP_SEE_OTHER);
        }
        $embed = $request->query->get('embed');
        return $this->redirectToRoute('app_category_index', $embed === '1' ? ['embed' => 1] : [], Response::HTTP_SEE_OTHER);
    }
}
