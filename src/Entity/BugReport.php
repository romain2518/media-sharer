<?php

namespace App\Entity;

use App\Repository\BugReportRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: BugReportRepository::class)]
#[ORM\HasLifecycleCallbacks]
class BugReport
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups('api_bug-report')]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\Url(message: 'Veuillez fournir une URL valide.')]
    #[Assert\Length(
        min: 10, // 'http://" + 3 characters
        max: 255,
        minMessage: 'L\'URL doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'L\'URL doit contenir au maximum {{ limit }} caractères.',
    )]
    #[Assert\NotBlank(message: 'Cette valeur est obligatoire.')]
    #[Groups('api_bug-report')]
    private ?string $url = null;

    #[ORM\Column(length: 255)]
    #[Assert\Length(
        min: 5,
        max: 255,
        minMessage: 'Le commentaire doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le commentaire doit contenir au maximum {{ limit }} caractères.',
    )]
    #[Assert\NotBlank(message: 'Cette valeur est obligatoire.')]
    #[Groups('api_bug-report')]
    private ?string $comment = null;

    #[ORM\Column]
    #[Groups('api_bug-report')]
    private ?bool $isProcessed = false;

    #[ORM\Column]
    #[Groups('api_bug-report')]
    private ?bool $isImportant = false;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups('api_bug-report')]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups('api_bug-report')]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\ManyToOne(inversedBy: 'bugReports', fetch: 'EAGER')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups('api_bug-report')]
    private ?User $user = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(string $url): self
    {
        $this->url = $url;

        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(string $comment): self
    {
        $this->comment = $comment;

        return $this;
    }

    public function isProcessed(): ?bool
    {
        return $this->isProcessed;
    }

    public function setIsProcessed(bool $isProcessed): self
    {
        $this->isProcessed = $isProcessed;

        return $this;
    }

    public function isImportant(): ?bool
    {
        return $this->isImportant;
    }

    public function setIsImportant(bool $isImportant): self
    {
        $this->isImportant = $isImportant;

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

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }
}
