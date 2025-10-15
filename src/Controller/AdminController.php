<?php

namespace App\Controller;

use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AdminController extends AbstractController
{
    #[Route('/admin', name: 'app_admin')]
    public function index(ProductRepository $productRepository): Response
    {
        return $this->render('admin/index.html.twig', [
            'page_title' => 'Admin Dashboard',
            'products' => $productRepository->findAll(),
        ]);
    }
}


