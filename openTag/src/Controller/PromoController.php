<?php

namespace App\Controller;

use App\DTO\PromoResponse;
use App\Entity\Promo;
use App\Message\SendModerator;
use App\Services\PromoService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class PromoController extends AbstractController
{

    /**
     * @var Request
     */
    private $request;

    /**
     * @var MessageBusInterface
     */
    private $bus;

    /**
     * @var ValidatorInterface
     */
    private $validator;

    /**
     * @var PromoService
     */
    private $promoService;

    /**
     * PromoController constructor.
     * @param RequestStack $requestStack
     * @param MessageBusInterface $bus
     * @param ValidatorInterface $validator
     * @param PromoService $promoService
     */
    public function __construct(
        RequestStack $requestStack,
        MessageBusInterface $bus,
        ValidatorInterface $validator,
        PromoService $promoService
    )
    {
        $this->request = $requestStack->getCurrentRequest();
        $this->bus = $bus;
        $this->validator = $validator;
        $this->promoService = $promoService;
    }

    /**
     * @Route("/promo", name="promo", methods={"GET"})
     */
    public function getAll()
    {
        $promos = $this->promoService->getAll();

        $dto = [];
        foreach ($promos as $promo) {
            $dto[] = (new PromoResponse())
                ->setId($promo->getId())
                ->setTitle($promo->getTitle())
                ->setMainText($promo->getMainText())
                ->setCategory($promo->getCategory())
                ->setStatus($promo->getStatus())
                ->setActive($promo->getActive());
        }

        return $this->json($dto, Response::HTTP_OK);
    }

    /**
     * @return \Symfony\Component\HttpFoundation\Response
     * @Route("promo/create", name="promo_create", methods={"POST"})
     */
    public function create()
    {
        $promo = new Promo();
        //var_dump(json_decode($this->request->getContent(), true)); die;
        $request = json_decode($this->request->getContent(), true);
        $promo->setTitle($request['title'])
            ->setMainText($request['mainText'])
            ->setCategory($request['category']);

        $errors = $this->validator->validate($promo);

        if (count($errors) > 0) {
            $errorsString = (string) $errors;
            return $this->json($errorsString);
        }

        try {
            $id = $this->promoService->create($promo);
        } catch (\Throwable $exception) {
            return $this->json($exception->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->bus->dispatch(new SendModerator($id));

        return $this->json(
            (new PromoResponse())
            ->setId($id)
            ->setTitle($this->request->get('title'))
                ->setTitle($request['title'])
                ->setMainText($request['mainText'])
                ->setCategory($request['category'])
                ->setStatus(Promo::STATUS_MODERATION)
                ->setActive(false),
            Response::HTTP_OK
        );
    }
}