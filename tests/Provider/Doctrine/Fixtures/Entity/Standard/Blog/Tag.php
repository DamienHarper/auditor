<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Stringable;

/**
 * @ORM\Entity
 *
 * @ORM\Table(name="tag")
 */
#[ORM\Entity]
#[ORM\Table(name: 'tag')]
class Tag implements Stringable
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
     * @ORM\ManyToMany(targetEntity="Post", mappedBy="tags", cascade={"persist", "remove"})
     */
    #[ORM\ManyToMany(targetEntity: 'Post', mappedBy: 'tags', cascade: ['persist', 'remove'])]
    protected $posts;

    public function __construct()
    {
        $this->posts = new ArrayCollection();
    }

    public function __toString(): string
    {
        return (string) $this->title;
    }

    public function __sleep()
    {
        return ['id', 'title'];
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
     * Add Post entity to collection.
     */
    public function addPost(Post $post): self
    {
        $this->posts[] = $post;

        return $this;
    }

    /**
     * Remove Post entity from collection.
     */
    public function removePost(Post $post): self
    {
        $this->posts->removeElement($post);

        return $this;
    }

    /**
     * Get Post entity collection.
     */
    public function getPosts(): Collection
    {
        return $this->posts;
    }
}
