<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Index(name: 'idx_email', fields: ['email'])]
#[ORM\Index(name: 'idx_pseudo', fields: ['pseudo'])]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['email'], message: 'Cette adresse mail est déjà utilisée.')]
#[Vich\Uploadable]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['api_conversation_list', 'api_conversation_show', 'api_user_light', 'api_user_detailed', 'api_user-report'])]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\Email(message: 'Veuillez fournir une adresse email valide.')]
    #[Assert\Length(
        min: 3,
        max: 180,
        minMessage: 'L\'adresse email doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'L\'adresse email doit contenir au maximum {{ limit }} caractères.',
    )]
    #[Assert\NotBlank(message: 'Cette valeur est obligatoire.')]
    private ?string $email = null;

    #[ORM\Column]
    #[Groups('api_user_detailed')]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(length: 30)]
    #[Assert\Length(
        min: 3,
        max: 30,
        minMessage: 'Le pseudo doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le pseudo doit contenir au maximum {{ limit }} caractères.',
    )]
    #[Assert\NotBlank(message: 'Cette valeur est obligatoire.')]
    #[Groups(['api_conversation_list', 'api_conversation_show', 'api_user_light', 'api_user_detailed', 'api_user-report'])]
    private ?string $pseudo = null;

    #[Vich\UploadableField(mapping: 'user_pictures', fileNameProperty: 'picturePath')]
    #[Assert\File(
        maxSize: "5M",
        mimeTypes: ["image/jpeg", "image/png"],
    )]
    private ?File $pictureFile = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['api_conversation_list', 'api_conversation_show', 'api_user_light', 'api_user_detailed', 'api_user-report'])]
    private ?string $picturePath = null;

    #[ORM\Column]
    private ?bool $isVerified = false;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['api_conversation_list', 'api_user_light', 'api_user_detailed'])]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Message::class, orphanRemoval: true)]
    private Collection $messages;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Status::class, orphanRemoval: true)]
    private Collection $statuses;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: UserReport::class, orphanRemoval: true)]
    private Collection $userReports;

    #[ORM\OneToMany(mappedBy: 'reportedUser', targetEntity: UserReport::class, orphanRemoval: true)]
    private Collection $relatedUserReports;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: BugReport::class, orphanRemoval: true)]
    private Collection $bugReports;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Ban::class)]
    private Collection $bans;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: PatchNote::class)]
    private Collection $patchNotes;

    #[ORM\ManyToMany(targetEntity: Conversation::class, inversedBy: 'users')]
    private Collection $conversations;

    #[ORM\ManyToMany(targetEntity: self::class)]
    private Collection $blockedUsers;

    public function __construct()
    {
        $this->messages = new ArrayCollection();
        $this->statuses = new ArrayCollection();
        $this->userReports = new ArrayCollection();
        $this->relatedUserReports = new ArrayCollection();
        $this->bugReports = new ArrayCollection();
        $this->bans = new ArrayCollection();
        $this->patchNotes = new ArrayCollection();
        $this->conversations = new ArrayCollection();
        $this->blockedUsers = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function setRoles(array $roles): self
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials()
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }

    public function getPseudo(): ?string
    {
        return $this->pseudo;
    }

    public function setPseudo(string $pseudo): self
    {
        $this->pseudo = $pseudo;

        return $this;
    }

    public function getPictureFile(): ?File
    {
        return $this->pictureFile;
    }

    public function setPictureFile(?File $pictureFile = null): self
    {
        $this->pictureFile = $pictureFile;

        if (null !== $pictureFile) {
            /// It is required that at least one field changes if you are using doctrine
            // otherwise the event listeners won't be called and the file is lost
            $this->updatedAt = new \DateTime();
        }

        return $this;
    }

    public function getPicturePath(): ?string
    {
        return $this->picturePath;
    }

    public function setPicturePath(?string $picturePath): self
    {
        $this->picturePath = $picturePath;

        return $this;
    }

    public function isVerified(): ?bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): self
    {
        $this->isVerified = $isVerified;

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

    /**
     * @return Collection<int, Message>
     */
    public function getMessages(): Collection
    {
        return $this->messages;
    }

    public function addMessage(Message $message): self
    {
        if (!$this->messages->contains($message)) {
            $this->messages->add($message);
            $message->setUser($this);
        }

        return $this;
    }

    public function removeMessage(Message $message): self
    {
        if ($this->messages->removeElement($message)) {
            // set the owning side to null (unless already changed)
            if ($message->getUser() === $this) {
                $message->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Status>
     */
    public function getStatuses(): Collection
    {
        return $this->statuses;
    }

    public function addStatus(Status $status): self
    {
        if (!$this->statuses->contains($status)) {
            $this->statuses->add($status);
            $status->setUser($this);
        }

        return $this;
    }

    public function removeStatus(Status $status): self
    {
        if ($this->statuses->removeElement($status)) {
            // set the owning side to null (unless already changed)
            if ($status->getUser() === $this) {
                $status->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, UserReport>
     */
    public function getUserReports(): Collection
    {
        return $this->userReports;
    }

    public function addUserReport(UserReport $userReport): self
    {
        if (!$this->userReports->contains($userReport)) {
            $this->userReports->add($userReport);
            $userReport->setUser($this);
        }

        return $this;
    }

    public function removeUserReport(UserReport $userReport): self
    {
        if ($this->userReports->removeElement($userReport)) {
            // set the owning side to null (unless already changed)
            if ($userReport->getUser() === $this) {
                $userReport->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, UserReport>
     */
    public function getRelatedUserReports(): Collection
    {
        return $this->relatedUserReports;
    }

    public function addRelatedUserReport(UserReport $relatedUserReport): self
    {
        if (!$this->relatedUserReports->contains($relatedUserReport)) {
            $this->relatedUserReports->add($relatedUserReport);
            $relatedUserReport->setReportedUser($this);
        }

        return $this;
    }

    public function removeRelatedUserReport(UserReport $relatedUserReport): self
    {
        if ($this->relatedUserReports->removeElement($relatedUserReport)) {
            // set the owning side to null (unless already changed)
            if ($relatedUserReport->getReportedUser() === $this) {
                $relatedUserReport->setReportedUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, BugReport>
     */
    public function getBugReports(): Collection
    {
        return $this->bugReports;
    }

    public function addBugReport(BugReport $bugReport): self
    {
        if (!$this->bugReports->contains($bugReport)) {
            $this->bugReports->add($bugReport);
            $bugReport->setUser($this);
        }

        return $this;
    }

    public function removeBugReport(BugReport $bugReport): self
    {
        if ($this->bugReports->removeElement($bugReport)) {
            // set the owning side to null (unless already changed)
            if ($bugReport->getUser() === $this) {
                $bugReport->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Ban>
     */
    public function getBans(): Collection
    {
        return $this->bans;
    }

    public function addBan(Ban $ban): self
    {
        if (!$this->bans->contains($ban)) {
            $this->bans->add($ban);
            $ban->setUser($this);
        }

        return $this;
    }

    public function removeBan(Ban $ban): self
    {
        if ($this->bans->removeElement($ban)) {
            // set the owning side to null (unless already changed)
            if ($ban->getUser() === $this) {
                $ban->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, PatchNote>
     */
    public function getPatchNotes(): Collection
    {
        return $this->patchNotes;
    }

    public function addPatchNote(PatchNote $patchNote): self
    {
        if (!$this->patchNotes->contains($patchNote)) {
            $this->patchNotes->add($patchNote);
            $patchNote->setUser($this);
        }

        return $this;
    }

    public function removePatchNote(PatchNote $patchNote): self
    {
        if ($this->patchNotes->removeElement($patchNote)) {
            // set the owning side to null (unless already changed)
            if ($patchNote->getUser() === $this) {
                $patchNote->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Conversation>
     */
    public function getConversations(): Collection
    {
        return $this->conversations;
    }

    public function addConversation(Conversation $conversation): self
    {
        if (!$this->conversations->contains($conversation)) {
            $this->conversations->add($conversation);
        }

        return $this;
    }

    public function removeConversation(Conversation $conversation): self
    {
        $this->conversations->removeElement($conversation);

        return $this;
    }

    /**
     * @return Collection<int, self>
     */
    public function getBlockedUsers(): Collection
    {
        return $this->blockedUsers;
    }

    public function addBlockedUser(self $blockedUser): self
    {
        if (!$this->blockedUsers->contains($blockedUser)) {
            $this->blockedUsers->add($blockedUser);
        }

        return $this;
    }

    public function removeBlockedUser(self $blockedUser): self
    {
        $this->blockedUsers->removeElement($blockedUser);

        return $this;
    }
}
