<?php

namespace App\Controller;

use App\Entity\Product;
use App\Form\ProductType;
use App\Repository\ProductRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/product")
 */
class ProductController extends AbstractController
{
    /**
     * @Route("/{page<\d+>}", name="app_product_index", methods={"GET"})
     */
    public function index(Request $request,ProductRepository $productRepository,int $page = 1): Response
    {
        $pageSize = 10;
        $paginator = new Paginator($productRepository->filter());
        $totalItems = count($paginator);
        $numOfPages = ceil($totalItems / $pageSize);
        $products = $paginator
            ->getQuery()
            ->setFirstResult($pageSize * ($page - 1)) // set the offset
            ->setMaxResults($pageSize) // set the limit
            ->getResult();

        return $this->render('product/index.html.twig', [
            'products' => $products,
            'numOfPages' => $numOfPages

        ]);
    }

    /**
     * @Route("/new", name="app_product_new", methods={"GET", "POST"})
     */
    public function new(Request $request, ProductRepository $productRepository): Response
    {
        $product = new Product();
        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $product->setImage("None");
            $productRepository->add($product);
            return $this->uploadImage($form, $productRepository, $product);
        }

        return $this->renderForm('product/new.html.twig', [
            'product' => $product,
            'form' => $form,
        ]);

    }

    /**
     * @Route("/{id}", name="app_product_show", methods={"GET"})
     */
    public function show(Product $product): Response
    {
        return $this->render('product/show.html.twig', [
            'product' => $product,
        ]);
    }

    /**
     * @Route("/{id}/edit", name="app_product_edit", methods={"GET", "POST"})
     */
    public function edit(Request $request, Product $product, ProductRepository $productRepository): Response
    {
        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $productRepository->add($product, true);
            return $this->uploadImage($form, $productRepository, $product);
        }

        return $this->renderForm('product/edit.html.twig', [
            'product' => $product,
            'form' => $form,
        ]);
    }

    /**
     * @Route("/{id}", name="app_product_delete", methods={"POST"})
     */
    public function delete(Request $request, Product $product, ProductRepository $productRepository): Response
    {
        if ($this->isCsrfTokenValid('delete'.$product->getId(), $request->request->get('_token'))) {
            $productRepository->remove($product, true);
        }

        return $this->redirectToRoute('app_product_index', [], Response::HTTP_SEE_OTHER);
    }

    /**
     * @param \Symfony\Component\Form\FormInterface $form
     * @param ProductRepository $productRepository
     * @param Product $product
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function uploadImage(\Symfony\Component\Form\FormInterface $form, ProductRepository $productRepository, Product $product): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        $productImg = $form->get('Image')->getData();
        if ($productImg) {
            $productRepository->add($product, true);
            $newFilename = $product->getId() . '.' . $productImg->guessExtension();
            try {
                $productImg->move(
                    $this->getParameter('product_directory'),
                    $newFilename
                );
            } catch (FileException $e) {
            }
            $product->setImage($newFilename);
        }
        $productRepository->add($product, true);
        return $this->redirectToRoute('app_product_index', [], Response::HTTP_SEE_OTHER);
    }
}
