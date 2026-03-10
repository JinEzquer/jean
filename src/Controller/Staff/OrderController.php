<?php

namespace App\Controller\Staff;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\User;
use App\Form\OrderType;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use App\Repository\UserRepository;
use App\Service\ActivityLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/staff/orders')]
#[IsGranted('ROLE_STAFF')]
class OrderController extends AbstractController
{
    private OrderRepository $orderRepository;
    private ProductRepository $productRepository;
    private UserRepository $userRepository;
    private EntityManagerInterface $entityManager;
    private ActivityLogger $activityLogger;

    public function __construct(
        OrderRepository $orderRepository,
        ProductRepository $productRepository,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        ActivityLogger $activityLogger
    ) {
        $this->orderRepository = $orderRepository;
        $this->productRepository = $productRepository;
        $this->userRepository = $userRepository;
        $this->entityManager = $entityManager;
        $this->activityLogger = $activityLogger;
    }

    #[Route('', name: 'staff_order_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $sort = $request->query->get('sort', 'DESC');
        $sort = in_array(strtoupper($sort), ['ASC', 'DESC']) ? strtoupper($sort) : 'DESC';
        
        $orders = $this->orderRepository->findAllSortedByDate($sort);

        return $this->render('staff/order/index.html.twig', [
            'orders' => $orders,
            'page_title' => 'Orders',
            'current_sort' => $sort,
        ]);
    }

    #[Route('/new', name: 'staff_order_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $order = new Order();
        // Add at least one empty order item so the form displays properly
        $orderItem = new OrderItem();
        $order->addOrderItem($orderItem);
        
        $form = $this->createForm(OrderType::class, $order);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Set the creator
            $user = $this->getUser();
            if ($user instanceof User) {
                $order->setCreatedBy($user);
            }
            
            // Ensure all order items have correct price and subtotal
            foreach ($order->getOrderItems() as $item) {
                if ($item->getProduct() && $item->getProduct()->getPrice() !== null) {
                    $item->setPrice((string) $item->getProduct()->getPrice());
                    $item->setQuantity($item->getQuantity());
                }
            }
            
            // Calculate total from order items
            $order->calculateTotal();
            
            $this->entityManager->persist($order);
            $this->entityManager->flush();

            $user = $this->getUser();
            if ($user instanceof User) {
                $this->activityLogger->logRecordCreated('Order', $order->getId(), "Order #{$order->getId()}", $user);
            }

            $this->addFlash('success', 'Order created successfully.');
            return $this->redirectToRoute('staff_order_index');
        }

        return $this->render('staff/order/new.html.twig', [
            'page_title' => 'Create New Order',
            'orderForm' => $form->createView(),
            'products' => $this->productRepository->findAll(),
            'users' => $this->userRepository->findAll(),
        ]);
    }

    #[Route('/{id}', name: 'staff_order_show', methods: ['GET'])]
    public function show(int $id): Response
    {
        $order = $this->orderRepository->find($id);
        if (!$order) {
            $this->addFlash('warning', 'Order not found.');
            return $this->redirectToRoute('staff_order_index');
        }

        return $this->render('staff/order/show.html.twig', [
            'page_title' => 'Order Details',
            'order' => $order,
        ]);
    }

    #[Route('/{id}/edit', name: 'staff_order_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, int $id): Response
    {
        $order = $this->orderRepository->find($id);
        if (!$order) {
            $this->addFlash('warning', 'Order not found.');
            return $this->redirectToRoute('staff_order_index');
        }

        // Only allow editing pending orders
        if (!$order->isPending()) {
            $this->addFlash('error', 'Only pending orders can be edited.');
            return $this->redirectToRoute('staff_order_show', ['id' => $id]);
        }

        $originalStatus = $order->getStatus();
        $form = $this->createForm(OrderType::class, $order);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Ensure all order items have correct price and subtotal
            foreach ($order->getOrderItems() as $item) {
                if ($item->getProduct() && $item->getProduct()->getPrice() !== null) {
                    $item->setPrice((string) $item->getProduct()->getPrice());
                    $item->setQuantity($item->getQuantity());
                }
            }
            
            $order->calculateTotal();
            $order->setUpdatedAt(new \DateTimeImmutable());
            
            $this->entityManager->flush();

            $user = $this->getUser();
            if ($user instanceof User) {
                $changes = [];
                if ($order->getStatus() !== $originalStatus) {
                    $changes['status'] = ['old' => $originalStatus, 'new' => $order->getStatus()];
                }
                if (!empty($changes)) {
                    $this->activityLogger->logRecordUpdated('Order', $order->getId(), "Order #{$order->getId()}", $changes, $user);
                }
            }

            $this->addFlash('success', 'Order updated successfully.');
            return $this->redirectToRoute('staff_order_show', ['id' => $id]);
        }

        return $this->render('staff/order/edit.html.twig', [
            'page_title' => 'Edit Order',
            'order' => $order,
            'orderForm' => $form->createView(),
            'products' => $this->productRepository->findAll(),
            'users' => $this->userRepository->findAll(),
        ]);
    }

    #[Route('/{id}/approve', name: 'staff_order_approve', methods: ['POST'])]
    public function approve(int $id): Response
    {
        $order = $this->orderRepository->find($id);
        if (!$order) {
            $this->addFlash('error', 'Order not found.');
            return $this->redirectToRoute('staff_order_index');
        }

        if ($order->isApproved()) {
            $this->addFlash('warning', 'Order is already approved.');
            return $this->redirectToRoute('staff_order_show', ['id' => $id]);
        }

        $order->setStatus(Order::STATUS_APPROVED);
        $order->setUpdatedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        $user = $this->getUser();
        if ($user instanceof User) {
            $this->activityLogger->logRecordUpdated('Order', $order->getId(), "Order #{$order->getId()}", [
                'status' => ['old' => Order::STATUS_PENDING_APPROVAL, 'new' => Order::STATUS_APPROVED]
            ], $user);
        }

        $this->addFlash('success', 'Order approved successfully.');
        return $this->redirectToRoute('staff_order_show', ['id' => $id]);
    }

    #[Route('/{id}/reject', name: 'staff_order_reject', methods: ['POST'])]
    public function reject(int $id): Response
    {
        $order = $this->orderRepository->find($id);
        if (!$order) {
            $this->addFlash('error', 'Order not found.');
            return $this->redirectToRoute('staff_order_index');
        }

        if ($order->isCanceled()) {
            $this->addFlash('warning', 'Order is already canceled.');
            return $this->redirectToRoute('staff_order_show', ['id' => $id]);
        }

        $order->setStatus(Order::STATUS_CANCELED);
        $order->setUpdatedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        $user = $this->getUser();
        if ($user instanceof User) {
            $this->activityLogger->logRecordUpdated('Order', $order->getId(), "Order #{$order->getId()}", [
                'status' => ['old' => $order->getStatus(), 'new' => Order::STATUS_CANCELED]
            ], $user);
        }

        $this->addFlash('success', 'Order rejected successfully.');
        return $this->redirectToRoute('staff_order_show', ['id' => $id]);
    }

    #[Route('/{id}/delete', name: 'staff_order_delete', methods: ['POST'])]
    public function delete(Request $request, int $id): Response
    {
        $order = $this->orderRepository->find($id);
        if (!$order) {
            $this->addFlash('error', 'Order not found.');
            return $this->redirectToRoute('staff_order_index');
        }

        $user = $this->getUser();
        
        // Staff can delete orders created by other staff, but not admin-created orders
        if ($user instanceof User && $user->isStaff() && !$user->isAdmin()) {
            $createdBy = $order->getCreatedBy();
            // Check if order was created by an admin
            if ($createdBy && $createdBy->isAdmin()) {
                $this->addFlash('error', 'Only administrators can delete orders created by admins.');
                return $this->redirectToRoute('staff_order_show', ['id' => $id]);
            }
            // Staff can delete orders created by other staff or themselves
        }

        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('delete' . $order->getId(), $token)) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('staff_order_show', ['id' => $id]);
        }

        $orderId = $order->getId();
        $this->entityManager->remove($order);
        $this->entityManager->flush();

        if ($user instanceof User) {
            $this->activityLogger->logRecordDeleted('Order', $orderId, "Order #{$orderId}", $user);
        }

        $this->addFlash('success', 'Order deleted successfully.');
        return $this->redirectToRoute('staff_order_index');
    }
}

