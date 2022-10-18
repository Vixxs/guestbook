<?php

namespace App\Controller;

use App\Entity\Comment;
use App\Entity\Conference;
use App\Form\CommentFormType;
use App\Message\CommentMessage;
use App\Repository\CommentRepository;
use App\Repository\ConferenceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Messenger\MessageBusInterface;
use Twig\Environment;

class ConferenceController extends AbstractController
{
    private Environment $twig;
    private MessageBusInterface $bus;
    private EntityManagerInterface $entityManager;

    public function __construct(Environment $twig, EntityManagerInterface $entityManager, MessageBusInterface $bus)
    {
        $this->twig =  $twig;
        $this->entityManager = $entityManager;
        $this->bus = $bus;
    }

    #[Route('/', 'homepage')]
    public function index(ConferenceRepository $conferenceRepository): Response
    {
        $response =  new Response(
            $this->twig->render('conference/index.html.twig', [
                'conferences' => $conferenceRepository->findAll()
            ])
        );
        $response->setSharedMaxAge(3600);
        return $response;
    }

    #[Route('/conference/{slug}', 'conference')]
    public function show(Request $request, Conference $conference, ConferenceRepository $conferenceRepository, CommentRepository $commentRepository, string $photoDir): Response
    {

        $comment = new Comment();
        $form = $this->createForm(CommentFormType::class, $comment);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()){
            $comment->setConference($conference);
            // Save in db
            if ($photo = $form['photo']->getData()){
                $filename = bin2hex(random_bytes(6)) . '.' . $photo->guessExtension();
                try {
                    $photo->move($photoDir, $filename);
                }
                catch (FileException $e){

                }
                $comment->setPhotoFilename($filename);
            }

            $this->entityManager->persist($comment);
            $this->entityManager->flush();

            $context = [
                'user_ip' => $request->getClientIp(),
                'user_agent' => $request->headers->get('user-agent'),
                'referrer' => $request->headers->get('referrer'),
                'permalink' => $request->getUri(),
            ];

            $this->bus->dispatch(new CommentMessage($comment->getId(), $context));

            return $this->redirectToRoute('conference', ['slug' => $conference->getSlug()]);
        }


        $offset = max(0, $request->query->getInt('offset', 0));
        $paginator = $commentRepository->getCommentPaginator($conference, $offset);
        return new Response(
            $this->twig->render('conference/show.html.twig', [
                    'conference' => $conference,
                    'comments' => $paginator,
                    'previous' => $offset - CommentRepository::PAGINATOR_PER_PAGE,
                    'next' => min(count($paginator), $offset + CommentRepository::PAGINATOR_PER_PAGE),
                    'comment_form' => $form->createView(),
                ]
            )
        );
    }

    #[Route('/conference_header', name: 'conference_header')]
    public function conferenceHeader(ConferenceRepository $conferenceRepository){
        $conferences = $conferenceRepository->findAll();

        $response = new Response($this->twig->render('conference/header.html.twig', [
            'conferences' => $conferences,
        ]));

        $response->setSharedMaxAge(3600);
        return $response;
    }

}
