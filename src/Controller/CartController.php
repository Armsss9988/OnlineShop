<?php

namespace App\Controller;

use App\Entity\Order;
use App\Entity\OrderDetail;
use App\Entity\Product;
use App\Repository\OrderDetailRepository;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use Doctrine\Persistence\ManagerRegistry;
use http\Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CartController extends AbstractController
{
    /**
     * @Route("/cart", name="app_cart")
     */
    public function index(): Response
    {
        return $this->render('cart/index.html.twig', [
            'controller_name' => 'CartController',
        ]);
    }
    /**
     * @Route("/addCart/{id}", name="app_add_cart", methods={"GET"})
     */
    public function addCart(Product $product, Request $request)
    {
        $session = $request->getSession();
        $quantity = (int)$request->query->get('quantity');

        //check if cart is empty
        if (!$session->has('cartElements')) {
            //if it is empty, create an array of pairs (prod Id & quantity) to store first cart element.
            $cartElements = array($product->getId() => $quantity);
            //save the array to the session for the first time.
            $session->set('cartElements', $cartElements);
        } else {
            $cartElements = $session->get('cartElements');
            //Add new product after the first time. (would UPDATE new quantity for added product)
            $cartElements = array($product->getId() => $quantity) + $cartElements;
            //Re-save cart Elements back to session again (after update/append new product to shopping cart)
            $session->set('cartElements', $cartElements);
        }
        return $this->redirectToRoute('app_product_index');
    }
    /**
     * @Route("/reviewCart", name="app_review_cart", methods={"GET"})
     */
    public function reviewCart(Request $request): Response
    {
        $session = $request->getSession();
        if ($session->has('cartElements')) {
            $cartElements = $session->get('cartElements');
        } else
            $cartElements = [];
        return $this->json($cartElements);
    }

    /**
     * @Route("/checkoutCart", name="app_checkout_cart", methods={"GET"})
     */
    public function checkoutCart(Request               $request,
                                 OrderDetailRepository $orderDetailRepository,
                                 OrderRepository       $orderRepository,
                                 ProductRepository     $productRepository,
                                 ManagerRegistry       $mr): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $entityManager = $mr->getManager();
        $session = $request->getSession(); //get a session
        // check if session has elements in cart
        if ($session->has('cartElements') && !empty($session->get('cartElements'))) {
            try {
                // start transaction!
                $entityManager->getConnection()->beginTransaction();
                $cartElements = $session->get('cartElements');

                //Create new Order and fill info for it. (Skip Total temporarily for now)
                $order = new Order();
                date_default_timezone_set('Asia/Ho_Chi_Minh');
                $order->setOrderDate(new \DateTime());
                /** @var \App\Entity\User $user */
                $user = $this->getUser();
                $order->setUser($user);
                $orderRepository->add($order, true); //flush here first to have ID in Order in DB.

                //Create all Order Details for the above Order
                $total = 0;
                foreach ($cartElements as $product_id => $quantity) {
                    $product = $productRepository->find($product_id);
                    //create each Order Detail
                    $orderDetail = new OrderDetail();
                    $orderDetail->setOrderNo($order);
                    $orderDetail->setProduct($product);
                    $orderDetail->setQuantity($quantity);
                    $orderDetailRepository->add($orderDetail);

                    $total += $product->getPrice() * $quantity;
                }
                $order->setTotal($total);
                $orderRepository->add($order);
                // flush all new changes (all order details and update order's total) to DB
                $entityManager->flush();

                // Commit all changes if all changes are OK
                $entityManager->getConnection()->commit();

                // Clean up/Empty the cart data (in session) after all.
                $session->remove('cartElements');
            } catch (Exception $e) {
                // If any change above got trouble, we roll back (undo) all changes made above!
                $entityManager->getConnection()->rollBack();
            }
            return $this->render('cart/index.html.twig');
        } else
            return new Response("Nothing in cart to checkout!");
    }




}
