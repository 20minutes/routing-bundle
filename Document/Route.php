<?php

namespace Symfony\Cmf\Bundle\RoutingExtraBundle\Document;

use Symfony\Component\Routing\Route as SymfonyRoute;
use Doctrine\ODM\PHPCR\Mapping\Annotations as PHPCRODM;
use Symfony\Cmf\Component\Routing\RouteObjectInterface;

/**
 * Default document for routing table entries that work with the DoctrineRouter.
 *
 * This needs the IdPrefix service to run and setPrefix whenever a route is
 * loaded. Otherwise the static prefix can not be determined.
 *
 * @author david.buchmann@liip.ch
 *
 * @PHPCRODM\Document(referenceable=true,repositoryClass="Symfony\Cmf\Bundle\RoutingExtraBundle\Document\RouteRepository")
 */
class Route extends SymfonyRoute implements RouteObjectInterface
{
    /**
     * @PHPCRODM\ParentDocument
     */
    protected $parent;
    /**
     * @PHPCRODM\Nodename
     */
    protected $name;

    /**
     * The full repository path to this route object
     * TODO: the strategy=parent argument should not be needed, we do have a ParentDocument annotation
     * @PHPCRODM\Id(strategy="parent")
     */
    protected $path;

    /**
     * The referenced document
     *
     * @PHPCRODM\ReferenceOne
     */
    protected $routeContent;

    /**
     * The part of the phpcr path that is not part of the url
     * @var string
     */
    protected $idPrefix;

    /**
     * Variable pattern part. The static part of the pattern is the id without the prefix.
     * @PHPCRODM\String
     */
    protected $variablePattern;

    /**
     * @var \Doctrine\ODM\PHPCR\MultivaluePropertyCollection
     * @PHPCRODM\String(multivalue=true)
     */
    protected $defaultsKeys;
    /**
     * @var \Doctrine\ODM\PHPCR\MultivaluePropertyCollection
     * @PHPCRODM\String(multivalue=true)
     */
    protected $defaultsValues;
    /**
     * @var \Doctrine\ODM\PHPCR\MultivaluePropertyCollection
     * @PHPCRODM\String(multivalue=true)
     */
    protected $requirementsKeys;
    /**
     * @var \Doctrine\ODM\PHPCR\MultivaluePropertyCollection
     * @PHPCRODM\String(multivalue=true)
     */
    protected $requirementsValues;
    /**
     * @var \Doctrine\ODM\PHPCR\MultivaluePropertyCollection
     * @PHPCRODM\String(multivalue=true)
     */
    protected $optionsKeys;
    /**
     * @var \Doctrine\ODM\PHPCR\MultivaluePropertyCollection
     * @PHPCRODM\String(multivalue=true)
     */
    protected $optionsValues;

    protected $needRecompile = false;

    /**
     * Overwrite to be able to create route without pattern
     */
    public function __construct()
    {
        $this->initArrays();
    }

    /**
     * Set the parent document and name of this route entry. Only allowed when
     * creating a new item!
     *
     * The url will be the url of the parent plus the supplied name.
     */
    public function setPosition($parent, $name)
    {
        $this->parent = $parent;
        $this->name = $name;
    }
    /**
     * Get the repository path of this url entry
     */
    public function getPath()
    {
      return $this->path;
    }

    public function setPrefix($idPrefix)
    {
        $this->idPrefix = $idPrefix;
    }

    /**
     * {@inheritDoc}
     */
    public function getStaticPrefix()
    {
        if (0 == strlen($this->idPrefix)) {
            throw new \LogicException('Can not determine the prefix. Either this is a new, unpersisted document or the listener that calls setPrefix is not set up correctly.');
        }
        if (strncmp($this->getPath(), $this->idPrefix, strlen($this->idPrefix))) {
            throw new \LogicException("The id prefix '".$this->idPrefix."' does not match the route document path '".$this->getPath()."'");
        }
        $url = substr($this->getPath(), strlen($this->idPrefix));
        if (empty($url)) {
            $url = '/';
        }
        return $url;
    }

    /**
     * Set the document this url points to
     */
    public function setRouteContent($document)
    {
        $this->routeContent = $document;
    }

    /**
     * {@inheritDoc}
     */
    public function getRouteContent()
    {
        return $this->routeContent;
    }

    /**
     * {@inheritDoc}
     */
    public function getPattern()
    {
        return $this->getStaticPrefix() . $this->getVariablePattern();
    }

    /**
     * {@inheritDoc}
     *
     * It is recommended to use setVariablePattern to just set the part after
     * the fixed part that follows from the repository path. If you use this
     * method, it will ensure the start of the pattern matches the repository
     * path (id) of this route document. Make sure to persist the route before
     * setting the pattern to have the id field initialized.
     */
    public function setPattern($pattern)
    {
        $len = strlen($this->getStaticPrefix());

        if (strncmp($this->getStaticPrefix(), $pattern, $len)) {
            throw new \InvalidArgumentException('You can not set a pattern for the route document that does not match its repository path. First move it to the correct path.');
        }
        return $this->setVariablePattern(substr($pattern, $len));
    }

    /**
     * @return string the variable part of the url pattern
     */
    public function getVariablePattern()
    {
        return $this->variablePattern;
    }

    /**
     * @param string $variablePattern the variable part of the url pattern
     * @return Route
     */
    public function setVariablePattern($variablePattern)
    {
        $this->variablePattern = $variablePattern;
        $this->needRecompile = true;
    }

    /**
     * {@inheritDoc}
     *
     * Overwritten to make sure the route is recompiled if the pattern was changed
     */
    public function compile()
    {
        if ($this->needRecompile) {
            // calling parent::setPattern just to let it set compiled=null. the parent $pattern field is never used
            parent::setPattern($this->getStaticPrefix() . $this->getVariablePattern());
        }
        return parent::compile();
    }

    // workaround for the missing hashmaps in phpcr-odm

    /**
     * @PHPCRODM\PostLoad
     */
    public function initArrays()
    {
        // phpcr-odm makes this a property collection. for some reason
        // array_combine does not work with ArrayAccess objects
        // if there are no values in a multivalue property, we don't get an
        // empty collection assigned but null

        if ($this->defaultsValues && count($this->defaultsValues)) {
            $this->setDefaults(array_combine(
                $this->defaultsKeys->getValues(),
                $this->defaultsValues->getValues())
            );
        } else {
            $this->setDefaults(array());
        }
        if ($this->requirementsValues && count($this->requirementsValues)) {
            $this->setRequirements(array_combine(
                $this->requirementsKeys->getValues(),
                $this->requirementsValues->getValues())
            );
        } else {
            $this->setRequirements(array());
        }
        // parent class will add compiler_class option back in if it was stripped out
        if ($this->optionsValues && count($this->optionsValues)) {
            $this->setOptions(array_combine(
                $this->optionsKeys->getValues(),
                $this->optionsValues->getValues())
            );
        } else {
            $this->setOptions(array());
        }
    }

    /**
     * @PHPCRODM\PreUpdate
     * @PHPCRODM\PrePersist
     */
    public function prepareArrays()
    {
        $defaults = $this->getDefaults();
        $this->defaultsKeys = array_keys($defaults);
        $this->defaultsValues = array_values($defaults);

        $requirements = $this->getRequirements();
        $this->requirementsKeys = array_keys($requirements);
        $this->requirementsValues = array_values($requirements);

        $options = $this->getOptions();
        // avoid storing the default value for the compiler, in case this ever changes in code
        // would be nice if those where class constants of the symfony route instead of hardcoded strings
        if ('Symfony\\Component\\Routing\\RouteCompiler' == $options['compiler_class']) {
            unset($options['compiler_class']);
        }
        $this->optionsKeys = array_keys($options);
        $this->optionsValues = array_values($options);
    }
}
