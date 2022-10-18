<?php
namespace App\MessageHandler;

use App\Entity\Comment;
use App\ImageOptimizer;
use App\Message\CommentMessage;
use App\Repository\CommentRepository;
use App\SpamChecker;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\NotificationEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Workflow\WorkflowInterface;

class CommentMessageHandler implements MessageHandlerInterface
{
    private SpamChecker $spamChecker;
    private EntityManagerInterface $entityManager;
    private CommentRepository $commentRepository;
    private MessageBusInterface $bus;
    private WorkflowInterface $workflow;
    private LoggerInterface $logger;
    private string $adminEmail;
    private MailerInterface $mailer;
    private ImageOptimizer $imageOptimizer;
    private string $photoDir;

    public function __construct(EntityManagerInterface $entityManager, SpamChecker $spamChecker, CommentRepository $commentRepository, MessageBusInterface $bus, WorkflowInterface $commentStateMachine, LoggerInterface $logger, string $adminEmail, MailerInterface $mailer, string $photoDir, ImageOptimizer $imageOptimizer)
    {
        $this->entityManager = $entityManager;
        $this->spamChecker = $spamChecker;
        $this->commentRepository = $commentRepository;
        $this->bus = $bus;
        $this->workflow = $commentStateMachine;
        $this->logger = $logger;
        $this->adminEmail = $adminEmail;
        $this->mailer = $mailer;
        $this->photoDir = $photoDir;
        $this->imageOptimizer = $imageOptimizer;

    }

    public function __invoke(CommentMessage $message)
    {
        error_log("Entering in messageHandler");
        $comment = $this->commentRepository->find($message->getId());
        if ($this->workflow->can($comment, 'accept')){
            $score = $this->spamChecker->getSpamScore($comment, $message->getContext());
            $transition = 'accept';
            if (2 == $score) {
                $transition = 'rejected';
            } elseif (1 == $score){
                $transition = "might_be_spam";
            }
            $this->workflow->apply($comment, $transition);
            $this->entityManager->flush();

            $this->bus->dispatch($message);
        } elseif ($this->workflow->can($comment, 'publish') || $this->workflow->can($comment, 'publish_ham')){
            $this->mailer->send((new NotificationEmail())
                ->subject('New comment posted')
                ->htmlTemplate('emails/comment_notification.html.twig')
                ->from($this->adminEmail)
                ->to($this->adminEmail)
                ->context(['comment' => $comment])
            );
        } elseif ($this->workflow->can($comment, 'optimize')){
            if ($comment->getPhotoFilename()){
                $this->imageOptimizer->resize($this->photoDir.'/'. $comment->getPhotoFilename());
            }
            $this->workflow->apply($comment, 'optimize');
            $this->entityManager->flush();
        } elseif ($this->logger){
            $this->logger->debug('Dropping comment message', ['comment' => $comment->getId(), 'state' => $comment->getState()]);
        }
        error_log("Exit");
    }
}