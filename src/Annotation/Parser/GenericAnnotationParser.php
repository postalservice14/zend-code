<?php
/**
 * Zend Framework
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://framework.zend.com/license/new-bsd
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@zend.com so we can send you a copy immediately.
 *
 * @category   Zend
 * @package    Zend_Code
 * @subpackage Annotation
 */

namespace Zend\Code\Annotation\Parser;

use Traversable;
use Zend\Code\Annotation\AnnotationInterface;
use Zend\Code\Exception;
use Zend\EventManager\EventInterface;

/**
 * Generic annotation parser
 *
 * Expects registration of AnnotationInterface instances. Such instances
 * will be passed annotation content to their initialize() method, which
 * they are then responsible for parsing.
 *
 * @package    Zend_Code
 * @subpackage Annotation
 */
class GenericAnnotationParser implements ParserInterface
{
    /**
     * @var array
     */
    protected $aliases = array();

    /**
     * @var string[]
     */
    protected $annotationNames = array();

    /**
     * @var AnnotationInterface[]
     */
    protected $annotations = array();

    /**
     * Listen to onCreateAnnotation, and attempt to return an annotation object 
     * instance.
     *
     * If the annotation class or alias is not registered, immediately returns 
     * false. Otherwise, resolves the class, clones it, and, if any content is
     * present, calls {@link AnnotationInterface::initialize()} with the 
     * content.
     * 
     * @param  EventInterface $e 
     * @return false|AnnotationInterface
     */
    public function onCreateAnnotation(EventInterface $e)
    {
        $class = $e->getParam('class', false);
        if (!$class || !$this->hasAnnotation($class)) {
            return false;
        }

        $content = $e->getParam('content', '');
        $content = trim($content, '()');

        if ($this->hasAlias($class)) {
            $class = $this->resolveAlias($class);
        }

        $index      = array_search($class, $this->annotationNames);
        $annotation = $this->annotations[$index];

        $newAnnotation = clone $annotation;
        if ($content) {
            $newAnnotation->initialize($content);
        }
        return $newAnnotation;
    }

    /**
     * Register annotations
     *
     * @param  string|AnnotationInterface $annotation String class name of an 
     *         AnnotationInterface implementation, or actual instance
     * @return GenericAnnotationParser
     * @throws Exception\InvalidArgumentException
     */
    public function registerAnnotation($annotation)
    {
        $class = false;
        if (is_string($annotation) && class_exists($annotation)) {
            $class      = $annotation;
            $annotation = new $annotation();
        }

        if (!$annotation instanceof AnnotationInterface) {
            throw new Exception\InvalidArgumentException(sprintf(
                '%s: expects an instance of %s\AnnotationInterface; received "%s"',
                __METHOD__,
                __NAMESPACE__,
                (is_object($annotation) ? get_class($annotation) : gettype($annotation))
            ));
        }

        $class = $class ?: get_class($annotation);

        if (in_array($class, $this->annotationNames)) {
            throw new Exception\InvalidArgumentException('An annotation for this class ' . $class . ' already exists');
        }

        $this->annotations[]     = $annotation;
        $this->annotationNames[] = $class;
    }

    /**
     * Register many annotations at once
     * 
     * @param  array|Traversable $annotations 
     * @return GenericAnnotationParser
     */
    public function registerAnnotations($annotations)
    {
        if (!is_array($annotations) && !$annotations instanceof Traversable) {
            throw new Exception\InvalidArgumentException(sprintf(
                '%s: expects an array or Traversable; received "%s"',
                __METHOD__,
                (is_object($annotations) ? get_class($annotations) : gettype($annotations))
            ));
        }

        foreach ($annotations as $annotation) {
            $this->registerAnnotation($annotation);
        }
        return $this;
    }

    /**
     * Checks if the manager has annotations for a class
     *
     * @param  string $class
     * @return bool
     */
    public function hasAnnotation($class)
    {
        if (in_array($class, $this->annotationNames)) {
            return true;
        }

        if ($this->hasAlias($class)) {
            return true;
        }

        return false;
    }

    /**
     * Alias an annotation name
     * 
     * @param  string $alias 
     * @param  string $class May be either a registered annotation name or another alias
     * @return GenericAnnotationParser
     */
    public function setAlias($alias, $class)
    {
        if (!in_array($class, $this->annotationNames) && !$this->hasAlias($class)) {
            throw new Exception\InvalidArgumentException(sprintf(
                '%s: Cannot alias "%s" to "%s", as class "%s" is not currently a registered annotation or alias',
                __METHOD__,
                $alias,
                $class,
                $class
            ));
        }

        $alias = $this->normalizeAlias($alias);
        $this->aliases[$alias] = $class;
        return $this;
    }

    /**
     * Normalize an alias name
     * 
     * @param  string $alias 
     * @return string
     */
    protected function normalizeAlias($alias)
    {
        return strtolower(str_replace(array('-', '_', ' ', '\\', '/'), '', $alias));
    }

    /**
     * Do we have an alias by the provided name?
     * 
     * @param  string $alias 
     * @return bool
     */
    protected function hasAlias($alias)
    {
        $alias = $this->normalizeAlias($alias);
        return (isset($this->aliases[$alias]));
    }

    /**
     * Resolve an alias to a class name
     * 
     * @param  string $alias 
     * @return string
     */
    protected function resolveAlias($alias)
    {
        do {
            $normalized = $this->normalizeAlias($alias);
            $class      = $this->aliases[$normalized];
        } while ($this->hasAlias($class));
        return $class;
    }
}
