<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog;

use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Stringable;

/**
 * @ORM\Entity
 *
 * @ORM\Table(name="post", indexes={@ORM\Index(name="fk_author_id", columns={"author_id"})})
 *
 * @Gedmo\SoftDeleteable(fieldName="deleted_at", timeAware=false, hardDelete=false)
 */
#[ORM\Entity]
#[ORM\Table(name: 'post')]
#[ORM\Index(name: 'fk_1_idx', columns: ['author_id'])]
class Post implements Stringable
{
    /**
     * @ORM\Id
     *
     * @ORM\Column(type="integer", options={"unsigned": true})
     *
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    #[ORM\Id]
    #[ORM\Column(type: 'integer', options: ['unsigned' => true])]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    protected $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    #[ORM\Column(type: 'string', length: 255)]
    protected $title;

    /**
     * @ORM\Column(type="text")
     */
    #[ORM\Column(type: 'text')]
    protected $body;

    /**
     * @Gedmo\Timestampable(on="create")
     *
     * @ORM\Column(type="datetime")
     */
    #[ORM\Column(type: 'datetime')]
    protected $created_at;

    /**
     * @ORM\Column(type="datetime", nullable=true, options={"default": NULL})
     */
    #[ORM\Column(type: 'datetime', nullable: true, options: ['default' => 'NULL'])]
    protected $deleted_at;

    /**
     * @ORM\Column(type="integer", options={"unsigned": true}, nullable=true)
     */
    #[ORM\Column(type: 'integer', options: ['unsigned' => true], nullable: true)]
    protected $author_id;

    /**
     * @ORM\OneToMany(targetEntity="Comment", mappedBy="post", cascade={"persist", "remove"})
     *
     * @ORM\JoinColumn(name="id", referencedColumnName="post_id", nullable=true)
     */
    #[ORM\OneToMany(targetEntity: 'Comment', mappedBy: 'post', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(name: 'id', referencedColumnName: 'post_id', nullable: true)]
    protected $comments;

    /**
     * @ORM\ManyToOne(targetEntity="Author", inversedBy="posts", cascade={"persist", "remove"})
     *
     * @ORM\JoinColumn(name="author_id", referencedColumnName="id", nullable=true)
     */
    #[ORM\ManyToOne(targetEntity: 'Author', inversedBy: 'posts', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(name: 'author_id', referencedColumnName: 'id', nullable: true)]
    protected $author;

    /**
     * @ORM\ManyToOne(targetEntity="Author", inversedBy="posts", cascade={"persist", "remove"})
     *
     * @ORM\JoinColumn(name="coauthor_id", referencedColumnName="id", nullable=true)
     */
    #[ORM\ManyToOne(targetEntity: 'Author', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(name: 'coauthor_id', referencedColumnName: 'id', nullable: true)]
    protected $coauthor;

    /**
     * @ORM\ManyToMany(targetEntity="Tag", inversedBy="posts", cascade={"persist", "remove"})
     *
     * @ORM\JoinTable(name="post__tag",
     *     joinColumns={@ORM\JoinColumn(name="post_id", referencedColumnName="id", nullable=false)},
     *     inverseJoinColumns={@ORM\JoinColumn(name="tag_id", referencedColumnName="id", nullable=false)}
     * )
     */
    #[ORM\ManyToMany(targetEntity: 'Tag', inversedBy: 'posts', cascade: ['persist', 'remove'])]
    //    #[ORM\JoinTable(name: 'post__tag', joinColumns: [new ORM\JoinColumn(name: 'post_id', referencedColumnName: 'id', nullable: false)], inverseJoinColumns: [new ORM\JoinColumn(name: 'tag_id', referencedColumnName: 'id', nullable: false)])]
    protected $tags;

    public function __construct()
    {
        $this->comments = new ArrayCollection();
        $this->tags = new ArrayCollection();
    }

    public function __toString(): string
    {
        return (string) $this->title;
    }

    public function __sleep()
    {
        return ['id', 'title', 'body', 'created_at', 'author_id'];
    }

    /**
     * Set the value of id.
     */
    public function setId(int $id): self
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Get the value of id.
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Set the value of title.
     */
    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Get the value of title.
     */
    public function getTitle(): ?string
    {
        return $this->title;
    }

    /**
     * Set the value of body.
     */
    public function setBody(string $body): self
    {
        $this->body = $body;

        return $this;
    }

    /**
     * Get the value of body.
     */
    public function getBody(): ?string
    {
        return $this->body;
    }

    /**
     * Set the value of created_at.
     */
    public function setCreatedAt(?DateTimeImmutable $created_at): self
    {
        $this->created_at = $created_at;

        return $this;
    }

    /**
     * Get the value of created_at.
     */
    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->created_at;
    }

    /**
     * Set the value of deleted_at.
     *
     * @param ?DateTime $deleted_at
     */
    public function setDeletedAt(?DateTimeImmutable $deleted_at): self
    {
        $this->deleted_at = $deleted_at;

        return $this;
    }

    /**
     * Get the value of deleted_at.
     */
    public function getDeletedAt(): ?DateTimeImmutable
    {
        return $this->deleted_at;
    }

    /**
     * Set the value of author_id.
     */
    public function setAuthorId(int $author_id): self
    {
        $this->author_id = $author_id;

        return $this;
    }

    /**
     * Get the value of author_id.
     */
    public function getAuthorId(): ?int
    {
        return $this->author_id;
    }

    /**
     * Add Comment entity to collection (one to many).
     */
    public function addComment(Comment $comment): self
    {
        $this->comments[] = $comment;

        return $this;
    }

    /**
     * Remove Comment entity from collection (one to many).
     */
    public function removeComment(Comment $comment): self
    {
        $this->comments->removeElement($comment);
        $comment->setPost(null);

        return $this;
    }

    /**
     * Get Comment entity collection (one to many).
     */
    public function getComments(): Collection
    {
        return $this->comments;
    }

    /**
     * Set Author entity (many to one).
     */
    public function setAuthor(?Author $author): self
    {
        $this->author = $author;

        return $this;
    }

    /**
     * Set Author entity (many to one).
     */
    public function setCoauthor(?Author $author): self
    {
        $this->coauthor = $author;

        return $this;
    }

    /**
     * Get Author entity (many to one).
     */
    public function getAuthor(): ?Author
    {
        return $this->author;
    }

    /**
     * Get Author entity (many to one).
     */
    public function getCoauthor(): ?Author
    {
        return $this->coauthor;
    }

    /**
     * Add Tag entity to collection.
     */
    public function addTag(Tag $tag): self
    {
        $tag->addPost($this);
        $this->tags[] = $tag;

        return $this;
    }

    /**
     * Remove Tag entity from collection.
     */
    public function removeTag(Tag $tag): self
    {
        $tag->removePost($this);
        $this->tags->removeElement($tag);

        return $this;
    }

    /**
     * Get Tag entity collection.
     */
    public function getTags(): Collection
    {
        return $this->tags;
    }
}
