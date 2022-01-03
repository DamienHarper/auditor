<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog;

use DateTime;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * @ORM\Entity
 * @ORM\Table(name="post", indexes={@ORM\Index(name="fk_author_id", columns={"author_id"})})
 * @Gedmo\SoftDeleteable(fieldName="deleted_at", timeAware=false)
 */
#[ORM\Entity, ORM\Table(name: 'post'), ORM\Index(columns: ['author_id'], name: 'fk_author_id')]
class Post
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer", options={"unsigned": true})
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    #[ORM\Id, ORM\GeneratedValue(strategy: 'IDENTITY'), ORM\Column(type: 'integer', options: ['unsigned' => true])]
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
     * @ORM\Column(type="datetime")
     */
    #[ORM\Column(type: 'datetime')]
    protected $created_at;

    /**
     * @ORM\Column(type="datetime", nullable=true, options={"default": NULL})
     */
    #[ORM\Column(type: 'datetime', nullable: true, options: ['default' => null])]
    protected $deleted_at;

    /**
     * @ORM\Column(type="integer", options={"unsigned": true}, nullable=true)
     */
    #[ORM\Column(type: 'integer', nullable: true, options: ['unsigned' => true])]
    protected $author_id;

    /**
     * @ORM\OneToMany(targetEntity="Comment", mappedBy="post", cascade={"persist", "remove"})
     * @ORM\JoinColumn(name="id", referencedColumnName="post_id", nullable=true)
     */
    #[ORM\OneToMany(mappedBy: 'post', targetEntity: 'Comment', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(name: 'id', referencedColumnName: 'post_id', nullable: true)]
    protected $comments;

    /**
     * @ORM\ManyToOne(targetEntity="Author", inversedBy="posts", cascade={"persist", "remove"})
     * @ORM\JoinColumn(name="author_id", referencedColumnName="id", nullable=true)
     */
    #[ORM\ManyToOne(targetEntity: 'Author', cascade: ['persist', 'remove'], inversedBy: 'posts')]
    #[ORM\JoinColumn(name: 'author_id', referencedColumnName: 'id', nullable: true)]
    protected $author;

    /**
     * @ORM\ManyToMany(targetEntity="Tag", inversedBy="posts", cascade={"persist", "remove"})
     * @ORM\JoinTable(name="post__tag",
     *     joinColumns={@ORM\JoinColumn(name="post_id", referencedColumnName="id", nullable=false)},
     *     inverseJoinColumns={@ORM\JoinColumn(name="tag_id", referencedColumnName="id", nullable=false)}
     * )
     */
    #[ORM\ManyToMany(targetEntity: 'Tag', inversedBy: 'posts', cascade: ['persist', 'remove'])]
    #[ORM\JoinTable(name: 'post__tag')]
    #[ORM\JoinColumn(name: 'post_id', referencedColumnName: 'id', nullable: false)]
    #[ORM\InverseJoinColumn(name: 'tag_id', referencedColumnName: 'id', nullable: false)]
    protected $tags;

    public function __construct()
    {
        $this->comments = new ArrayCollection();
        $this->tags = new ArrayCollection();
    }

    public function __toString()
    {
        return $this->title;
    }

    public function __sleep()
    {
        return ['id', 'title', 'body', 'created_at', 'author_id'];
    }

    /**
     * Set the value of id.
     *
     * @return Post
     */
    public function setId(int $id): self
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Get the value of id.
     *
     * @return int
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Set the value of title.
     *
     * @return Post
     */
    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Get the value of title.
     *
     * @return string
     */
    public function getTitle(): ?string
    {
        return $this->title;
    }

    /**
     * Set the value of body.
     *
     * @return Post
     */
    public function setBody(string $body): self
    {
        $this->body = $body;

        return $this;
    }

    /**
     * Get the value of body.
     *
     * @return string
     */
    public function getBody(): ?string
    {
        return $this->body;
    }

    /**
     * Set the value of created_at.
     *
     * @param ?DateTime $created_at
     *
     * @return Post
     */
    public function setCreatedAt(?DateTimeImmutable $created_at): self
    {
        $this->created_at = $created_at;

        return $this;
    }

    /**
     * Get the value of created_at.
     *
     * @return ?DateTime
     */
    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->created_at;
    }

    /**
     * Set the value of deleted_at.
     *
     * @param ?DateTime $deleted_at
     *
     * @return Post
     */
    public function setDeletedAt(?DateTimeImmutable $deleted_at): self
    {
        $this->deleted_at = $deleted_at;

        return $this;
    }

    /**
     * Get the value of deleted_at.
     *
     * @return ?DateTime
     */
    public function getDeletedAt(): ?DateTimeImmutable
    {
        return $this->deleted_at;
    }

    /**
     * Set the value of author_id.
     *
     * @return Post
     */
    public function setAuthorId(int $author_id): self
    {
        $this->author_id = $author_id;

        return $this;
    }

    /**
     * Get the value of author_id.
     *
     * @return int
     */
    public function getAuthorId(): ?int
    {
        return $this->author_id;
    }

    /**
     * Add Comment entity to collection (one to many).
     *
     * @return Post
     */
    public function addComment(Comment $comment): self
    {
        $this->comments[] = $comment;

        return $this;
    }

    /**
     * Remove Comment entity from collection (one to many).
     *
     * @return Post
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
     *
     * @param ?Author $author
     *
     * @return Post
     */
    public function setAuthor(?Author $author): self
    {
        $this->author = $author;

        return $this;
    }

    /**
     * Get Author entity (many to one).
     *
     * @return ?Author
     */
    public function getAuthor(): ?Author
    {
        return $this->author;
    }

    /**
     * Add Tag entity to collection.
     *
     * @return Post
     */
    public function addTag(Tag $tag): self
    {
        $tag->addPost($this);
        $this->tags[] = $tag;

        return $this;
    }

    /**
     * Remove Tag entity from collection.
     *
     * @return Post
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
