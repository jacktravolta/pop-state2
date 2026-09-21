<?php

namespace App\Controller\Api;

use App\Entity\Invoice;
use App\Repository\InvoiceRepository;
use App\Repository\InvoiceSettlementRepository;
use App\Service\BillingService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/invoices', name: 'api_invoice_')]
class ApiInvoiceController extends AbstractController
{
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(InvoiceRepository $repo): JsonResponse
    {
        $invoices = $repo->findBy([], ['id' => 'DESC']);
        $data = array_map(fn (Invoice $i) => $this->serialize($i), $invoices);

        return new JsonResponse(['data' => $data, 'total' => \count($data)]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Invoice $invoice, InvoiceSettlementRepository $pivotRepo): JsonResponse
    {
        return new JsonResponse($this->serialize($invoice, true, $pivotRepo));
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request, BillingService $billingService): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        if (empty($data['periodo']) || empty($data['emisor'])) {
            return new JsonResponse(['error' => 'Faltan campos obligatorios: periodo (YYYY-MM), emisor'], 400);
        }

        try {
            $result = $billingService->createInvoice(
                $data['periodo'],
                $data['emisor'],
                $data['receptor'] ?? null,
                $this->getUser(),
                isset($data['companyId']) ? (int) $data['companyId'] : null,
                isset($data['propertyId']) ? (int) $data['propertyId'] : null,
            );

            return new JsonResponse([
                'data' => array_map(fn (Invoice $i) => $this->serialize($i, true), $result['invoices']),
                'archivo_plano_preview' => substr($result['archivo_plano'], 0, 2000),
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }
    }

    private function serialize(Invoice $i, bool $withSettlements = false, ?InvoiceSettlementRepository $pivotRepo = null): array
    {
        $data = [
            'id' => $i->getId(),
            'folio' => $i->getFolio(),
            'emisor' => $i->getEmisor(),
            'receptor' => $i->getReceptor(),
            'periodo' => $i->getPeriodo(),
            'estado' => $i->getEstado(),
            'total' => $i->getTotal(),
            'observacion' => $i->getObservacion(),
            'createdAt' => $i->getCreatedAt()?->format('c'),
        ];

        if ($withSettlements) {
            $pivots = $i->getInvoiceSettlements()->toArray();
            if ($pivotRepo && empty($pivots) && $i->getId()) {
                $pivots = $pivotRepo->findBy(['invoice' => $i]);
            }
            $data['settlements'] = array_map(fn ($is) => [
                'id' => $is->getSettlement()->getId(),
                'direccion' => $is->getSettlement()->getProperty()->getDireccion(),
                'total' => $is->getSettlement()->getTotal(),
            ], $pivots);
        }

        return $data;
    }
}
