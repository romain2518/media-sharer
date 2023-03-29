<?php

namespace App\Entity;

use App\Repository\MessageRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: MessageRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Message
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['api_message', 'api_conversation_detailed'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\Length(
        min: 1,
        max: 255,
        minMessage: 'Le message doit contenir au moins {{ limit }} caractère.',
        maxMessage: 'Le message doit contenir au maximum {{ limit }} caractères.',
    )]
    #[Assert\NotBlank(message: 'Cette valeur est obligatoire.')]
    #[Groups(['api_message', 'api_conversation_detailed'])]
    private ?string $message = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['api_message', 'api_conversation_detailed'])]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['api_message', 'api_conversation_detailed'])]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\ManyToOne(inversedBy: 'messages')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups('api_message')]
    private ?Conversation $conversation = null;

    #[ORM\ManyToOne(inversedBy: 'messages')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['api_message', 'api_conversation_detailed'])]
    private ?User $user = null;

    /**
     * Used when deleting entity in order to set back the historical id (which is removed after deletion).
     */
    private ?int $historicalId = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(string $message): self
    {
        $this->message = $message;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function updateDatetimes(): void
    {
        if (null === $this->getCreatedAt()) { // => PrePersist
            $this->setCreatedAt(new \DateTime('now'));
        } else { // => PreUpdate
            $this->setUpdatedAt(new \DateTime('now'));
        } 
    }

    public function getConversation(): ?Conversation
    {
        return $this->conversation;
    }

    public function setConversation(?Conversation $conversation): self
    {
        $this->conversation = $conversation;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }

    #[ORM\PrePersist]
    public function checkConversationMessagesLimit()
    {
        // Current message is not persisted yet,
        // To reduce the message count to 50, we must accept only first 49 messages
        $messageCount = count($this->conversation->getMessages());
        $howManyToRemove = $messageCount - 49;
        
        if ($howManyToRemove < 1) {
            return;
        }
                
        // Remove old messages
        for ($i=$messageCount-1; $i > $messageCount-$howManyToRemove-1; $i--) { 
            $this->conversation->getMessages()[$i]->setConversation(null);
            $this->conversation->getMessages()->remove($i);
        }
    }

    #[ORM\PreRemove]
    public function preRemove(LifecycleEventArgs $args): void {
        $message = $args->getObject();
        $this->historicalId = $message->getId();
    }
    
    #[ORM\PostRemove]
    public function postRemove(): void {
        $this->id = $this->historicalId;
    }
}
