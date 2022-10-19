<?php

namespace App\Notification;

use App\Entity\Comment;
use Symfony\Component\Notifier\Bridge\Discord\DiscordOptions;
use Symfony\Component\Notifier\Bridge\Discord\Embeds\DiscordAuthorEmbedObject;
use Symfony\Component\Notifier\Bridge\Discord\Embeds\DiscordEmbed;
use Symfony\Component\Notifier\Bridge\Discord\Embeds\DiscordFieldEmbedObject;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\EmailMessage;
use Symfony\Component\Notifier\Notification\ChatNotificationInterface;
use Symfony\Component\Notifier\Notification\EmailNotificationInterface;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Recipient\EmailRecipientInterface;
use Symfony\Component\Notifier\Recipient\RecipientInterface;

class CommentReviewNotification extends Notification implements EmailNotificationInterface, ChatNotificationInterface
{
    private Comment $comment;

    private string $reviewUrl;

    public function __construct(Comment $comment, string $reviewUrl)
    {
        $this->comment = $comment;
        $this->reviewUrl = $reviewUrl;
        parent::__construct();
    }

    public function getChannels(RecipientInterface $recipient): array
    {
        return['email', 'chat/discord'];
    }

    public function asEmailMessage(EmailRecipientInterface $recipient, string $transport = null): ?EmailMessage
    {
        $message = EmailMessage::fromNotification($this, $recipient, $transport);
        $message->getMessage()
            ->htmlTemplate('emails/comment_notification.html.twig')
            ->context(['comment' => $this->comment])
        ;

        return $message;
    }

    public function asChatMessage(RecipientInterface $recipient, string $transport = null): ?ChatMessage
    {
        if ($transport != "discord") {
            return null;
        }
        $message = ChatMessage::fromNotification($this, $recipient, $transport);
        $message->options((new DiscordOptions())
            ->addEmbed((new DiscordEmbed())
                ->title('New comment posted!')
                ->author((new DiscordAuthorEmbedObject())
                    ->name($this->comment->getAuthor())
                )
                ->description($this->comment->getText())
                ->addField((new DiscordFieldEmbedObject())
                    ->name('Accept')
                    ->value($this->reviewUrl)
                )
                ->addField((new DiscordFieldEmbedObject())
                    ->name('Reject')
                    ->value($this->reviewUrl . '?reject=1')
                )
            )
        );
        return $message;
    }
}