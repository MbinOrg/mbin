<?php

declare(strict_types=1);

namespace App\Controller\User;

use App\Controller\AbstractController;
use App\DTO\UserFilterListDto;
use App\Entity\UserFilterList;
use App\Form\UserFilterListType;
use App\Security\Voter\FilterListVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class FilterListsController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[IsGranted('ROLE_USER')]
    public function __invoke(): Response
    {
        return $this->render('user/settings/filter_lists.html.twig');
    }

    #[IsGranted('ROLE_USER')]
    public function create(Request $request): Response
    {
        $dto = new UserFilterListDto();
        $dto->addEmptyWords();
        $form = $this->createForm(UserFilterListType::class, $dto);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UserFilterListDto $data */
            $data = $form->getData();
            $list = $this->createFromDto($data);

            $this->entityManager->persist($list);
            $this->entityManager->flush();

            return $this->redirectToRoute('user_settings_filter_lists');
        }

        return $this->render(
            'user/settings/filter_lists_create.html.twig',
            [
                'form' => $form->createView(),
            ],
            new Response(null, $form->isSubmitted() && !$form->isValid() ? 422 : 200)
        );
    }

    #[IsGranted('ROLE_USER')]
    #[IsGranted(FilterListVoter::EDIT, 'list')]
    public function edit(Request $request, #[MapEntity(id: 'id')] UserFilterList $list): Response
    {
        $dto = UserFilterListDto::fromList($list);
        $dto->addEmptyWords();
        $form = $this->createForm(UserFilterListType::class, $dto);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UserFilterListDto $data */
            $data = $form->getData();
            $list->name = $data->name;
            $list->expirationDate = $data->expirationDate;
            $list->feeds = $data->feeds;
            $list->comments = $data->comments;
            $list->profile = $data->profile;
            $list->words = $data->wordsToArray();
            $this->entityManager->persist($list);
            $this->entityManager->flush();

            return $this->redirectToRoute('user_settings_filter_lists');
        }

        return $this->render(
            'user/settings/filter_lists_edit.html.twig',
            [
                'form' => $form->createView(),
            ],
            new Response(null, $form->isSubmitted() && !$form->isValid() ? 422 : 200)
        );
    }

    #[IsGranted('ROLE_USER')]
    #[IsGranted(FilterListVoter::DELETE, 'list')]
    public function delete(#[MapEntity(id: 'id')] UserFilterList $list): Response
    {
        $this->entityManager->remove($list);
        $this->entityManager->flush();

        return $this->redirectToRoute('user_settings_filter_lists');
    }

    private function createFromDto(UserFilterListDto $data): UserFilterList
    {
        $list = new UserFilterList();
        $list->user = $this->getUserOrThrow();
        $list->name = $data->name;
        $list->expirationDate = $data->expirationDate;
        $list->feeds = $data->feeds;
        $list->comments = $data->comments;
        $list->profile = $data->profile;
        $list->words = $data->wordsToArray();

        return $list;
    }
}
