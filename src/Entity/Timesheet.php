<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use App\Timesheet\Calculator\RateCalculator;
use App\Timesheet\Util;

/**
 * @ORM\Table(name="kimai2_timesheet",
 *     indexes={
 *          @ORM\Index(columns={"user"}),
 *          @ORM\Index(columns={"activity_id"})
 *     }
 * )
 * @ORM\Entity(repositoryClass="App\Repository\TimesheetRepository")
 * @ORM\HasLifecycleCallbacks()
 * @App\Validator\Constraints\Timesheet
 */
class Timesheet implements EntityWithMetaFields
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="start_time", type="datetime", nullable=false)
     * @Assert\NotNull()
     */
    private $begin;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="end_time", type="datetime", nullable=true)
     */
    private $end;

    /**
     * @var string
     *
     * @ORM\Column(name="timezone", type="string", length=64, nullable=false)
     */
    private $timezone;

    /**
     * @var bool
     */
    private $localized = false;

    /**
     * @var int
     *
     * @ORM\Column(name="duration", type="integer", nullable=true)
     * @Assert\GreaterThanOrEqual(0)
     */
    private $duration = 0;

    /**
     * @var User
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\User")
     * @ORM\JoinColumn(name="`user`", referencedColumnName="id", onDelete="CASCADE", nullable=false)
     * @Assert\NotNull()
     */
    private $user;

    /**
     * @var Activity
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\Activity", inversedBy="timesheets")
     * @ORM\JoinColumn(onDelete="CASCADE", nullable=false)
     * @Assert\NotNull()
     */
    private $activity;

    /**
     * @var Project
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\Project", inversedBy="timesheets")
     * @ORM\JoinColumn(onDelete="CASCADE", nullable=false)
     * @Assert\NotNull()
     */
    private $project;

    /**
     * @var string
     *
     * @ORM\Column(name="description", type="text", length=65535, nullable=true)
     */
    private $description;

    /**
     * @var float
     *
     * @ORM\Column(name="rate", type="float", precision=10, scale=2, nullable=false)
     * @Assert\GreaterThanOrEqual(0)
     */
    private $rate = 0.00;

    // keep the trait include exactly here, for placing the column at the correct position
    use RatesTrait;

    /**
     * @var bool
     *
     * @ORM\Column(name="exported", type="boolean", nullable=false)
     * @Assert\NotNull()
     */
    private $exported = false;

    /**
     * @var Tag[]|ArrayCollection
     *
     * @ORM\ManyToMany(targetEntity="Tag", inversedBy="timesheets", cascade={"persist"})
     * @ORM\JoinTable(
     *  name="kimai2_timesheet_tags",
     *  joinColumns={
     *      @ORM\JoinColumn(name="timesheet_id", referencedColumnName="id")
     *  },
     *  inverseJoinColumns={
     *      @ORM\JoinColumn(name="tag_id", referencedColumnName="id")
     *  }
     * )
     */
    private $tags;

    /**
     * @var TimesheetMeta[]|Collection
     *
     * @ORM\OneToMany(targetEntity="App\Entity\TimesheetMeta", mappedBy="timesheet", cascade={"persist"})
     */
    private $meta;

    /**
     * Default constructor, initializes collections
     */
    public function __construct()
    {
        $this->tags = new ArrayCollection();
        $this->meta = new ArrayCollection();
    }

    /**
     * Get entry id, returns null for new entities which were not persisted.
     *
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Make sure begin and end date have the correct timezone.
     * This will be called once for each item after being loaded from the database.
     */
    protected function localizeDates()
    {
        if ($this->localized) {
            return;
        }

        if (null !== $this->begin) {
            $this->begin->setTimeZone(new \DateTimeZone($this->timezone));
        }

        if (null !== $this->end) {
            $this->end->setTimeZone(new \DateTimeZone($this->timezone));
        }

        $this->localized = true;
    }

    public function getBegin(): ?\DateTime
    {
        $this->localizeDates();

        return $this->begin;
    }

    /**
     * @param \DateTime $begin
     * @return Timesheet
     */
    public function setBegin(\DateTime $begin): Timesheet
    {
        $this->begin = $begin;
        $this->timezone = $begin->getTimezone()->getName();

        return $this;
    }

    public function getEnd(): ?\DateTime
    {
        $this->localizeDates();

        return $this->end;
    }

    /**
     * @param \DateTime $end
     * @return Timesheet
     */
    public function setEnd(?\DateTime $end): Timesheet
    {
        $this->end = $end;

        if (null === $end) {
            $this->duration = 0;
            $this->rate = 0.00;
        } else {
            $this->timezone = $end->getTimezone()->getName();
        }

        return $this;
    }

    /**
     * @param int $duration
     * @return Timesheet
     */
    public function setDuration($duration): Timesheet
    {
        $this->duration = $duration;

        return $this;
    }

    /**
     * Do not rely on the results of this method for running records.
     *
     * @return int
     */
    public function getDuration()
    {
        if (0 == $this->duration && null !== $this->begin)
        {
            return time() - $this->begin->getTimestamp();
        }
        else
        {
            return $this->duration;
        }
    }

    /**
     * @param User $user
     * @return Timesheet
     */
    public function setUser(User $user): Timesheet
    {
        $this->user = $user;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    /**
     * @param Activity $activity
     * @return Timesheet
     */
    public function setActivity($activity): Timesheet
    {
        $this->activity = $activity;

        return $this;
    }

    public function getActivity(): ?Activity
    {
        return $this->activity;
    }

    public function getProject(): ?Project
    {
        return $this->project;
    }

    /**
     * @param Project $project
     * @return Timesheet
     */
    public function setProject(Project $project): Timesheet
    {
        $this->project = $project;

        return $this;
    }

    /**
     * @param string $description
     * @return Timesheet
     */
    public function setDescription($description): Timesheet
    {
        $this->description = $description;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * @param float $rate
     * @return Timesheet
     */
    public function setRate($rate): Timesheet
    {
        $this->rate = $rate;

        return $this;
    }

    /**
     * @return float
     */
    public function getRate()
    {
        if (0 == $this->rate)
        {
            $rateCalculator = new RateCalculator([]);
            $fixedRate = $rateCalculator->findFixedRate($this);
            if (null !== $fixedRate)
            {
                return $fixedRate;
            }

            $hourlyRate = $rateCalculator->findHourlyRate($this);
            $factor = $rateCalculator->getRateFactor($this);

            $hourlyRate = (float) ($hourlyRate * $factor);
            $rate = Util::calculateRate($hourlyRate, $this->getDuration());

            return $rate;
        }
        else
        {
            return $this->rate;
        }
    }

    /**
     * @param Tag $tag
     * @return Timesheet
     */
    public function addTag(Tag $tag): Timesheet
    {
        if ($this->tags->contains($tag)) {
            return $this;
        }
        $this->tags->add($tag);
        $tag->addTimesheet($this);

        return $this;
    }

    /**
     * @param Tag $tag
     */
    public function removeTag(Tag $tag)
    {
        if (!$this->tags->contains($tag)) {
            return;
        }
        $this->tags->removeElement($tag);
        $tag->removeTimesheet($this);
    }

    /**
     * @return Collection<Tag>
     */
    public function getTags(): Collection
    {
        return $this->tags;
    }

    /**
     * @return string[]
     */
    public function getTagsAsArray()
    {
        return array_map(
            function (Tag $element) {
                return $element->getName();
            },
            $this->getTags()->toArray()
        );
    }

    /**
     * @return bool
     */
    public function isExported(): bool
    {
        return $this->exported;
    }

    /**
     * @param bool $exported
     * @return Timesheet
     */
    public function setExported(bool $exported): Timesheet
    {
        $this->exported = $exported;

        return $this;
    }

    /**
     * @return string
     */
    public function getTimezone(): string
    {
        return $this->timezone;
    }

    /**
     * BE WARNED: this method should NOT be used programmatically, there is very likely no reason for it!
     *
     * @internal
     * @deprecated since it was introduced, only meant for the initial migration. Will be removed with 1.0.
     * @param string $timezone
     * @return Timesheet
     */
    public function setTimezone(string $timezone): Timesheet
    {
        $this->timezone = $timezone;

        return $this;
    }

    /**
     * @internal only here for symfony forms
     * @return Collection|MetaTableTypeInterface[]
     */
    public function getMetaFields(): Collection
    {
        return $this->meta;
    }

    /**
     * @return MetaTableTypeInterface[]
     */
    public function getVisibleMetaFields(): array
    {
        $all = [];
        foreach ($this->meta as $meta) {
            if ($meta->isVisible()) {
                $all[] = $meta;
            }
        }

        return $all;
    }

    public function getMetaField(string $name): ?MetaTableTypeInterface
    {
        foreach ($this->meta as $field) {
            if ($field->getName() === $name) {
                return $field;
            }
        }

        return null;
    }

    public function setMetaField(MetaTableTypeInterface $meta): EntityWithMetaFields
    {
        if (null === ($current = $this->getMetaField($meta->getName()))) {
            $meta->setEntity($this);
            $this->meta->add($meta);

            return $this;
        }

        $current->merge($meta);

        return $this;
    }
}
