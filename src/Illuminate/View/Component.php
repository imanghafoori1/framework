<?php

namespace Illuminate\View;

use Closure;
use Illuminate\Container\Container;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

abstract class Component
{
    /**
     * The cache of public property names, keyed by class.
     *
     * @var array
     */
    protected static $propertyCache = [];

    /**
     * The cache of public method names, keyed by class.
     *
     * @var array
     */
    protected static $methodCache = [];

    /**
     * That properties / methods that should not be exposed to the component.
     *
     * @var array
     */
    protected $except = [];

    /**
     * The component attributes.
     *
     * @var \Illuminate\View\ComponentAttributeBag
     */
    public $attributes;

    /**
     * Get the view / view contents that represent the component.
     *
     * @return \Illuminate\View\View|string
     */
    abstract public function render();

    /**
     * Resolve the Blade view or view file that should be used when rendering the component.
     *
     * @return \Illuminate\View\View|string
     */
    public function resolveView()
    {
        $view = $this->render();

        if ($view instanceof View) {
            return $view;
        }

        $factory = Container::getInstance()->make('view');

        return $factory->exists($this->render())
                    ? $this->render()
                    : $this->createBladeViewFromString($factory, $this->render());
    }

    /**
     * Create a Blade view with the raw component string content.
     *
     * @param  \Illuminate\Contracts\View\Factory  $factory
     * @param  string  $contents
     * @return string
     */
    protected function createBladeViewFromString($factory, $contents)
    {
        $factory->addNamespace(
            '__components',
            $directory = Container::getInstance()['config']->get('view.compiled')
        );

        if (! file_exists($viewFile = $directory.'/'.sha1($contents).'.blade.php')) {
            file_put_contents($viewFile, $contents);
        }

        return '__components::'.basename($viewFile, '.blade.php');
    }

    /**
     * Get the data that should be supplied to the view.
     *
     * @author Freek Van der Herten
     * @author Brent Roose
     *
     * @return array
     */
    public function data()
    {
        $this->attributes = $this->attributes ?: new ComponentAttributeBag;

        return array_merge($this->extractPublicProperties(), $this->extractPublicMethods());
    }

    /**
     * Extract the public properties for the component.
     *
     * @return array
     */
    protected function extractPublicProperties()
    {
        $class = get_class($this);

        if (! isset(static::$propertyCache[$class])) {
            static::$propertyCache[$class] = $this->getNames(
                (new ReflectionClass($this))->getProperties(ReflectionProperty::IS_PUBLIC)
            );
        }

        return $this->collectAttributes(static::$propertyCache[$class], function ($property) {
            return $this->{$property};
        });
    }

    /**
     * Extract the public methods for the component.
     *
     * @return array
     */
    protected function extractPublicMethods()
    {
        $class = get_class($this);

        if (! isset(static::$methodCache[$class])) {
            static::$methodCache[$class] = $this->getNames(
                (new ReflectionClass($this))->getMethods(ReflectionMethod::IS_PUBLIC)
            );
        }

        return $this->collectAttributes(static::$methodCache[$class], function ($method) {
            return $this->createVariableFromMethod(new ReflectionMethod($this, $method));
        });
    }

    /**
     * Create a callable variable from the given method.
     *
     * @param  \ReflectionMethod  $method
     * @return mixed
     */
    protected function createVariableFromMethod(ReflectionMethod $method)
    {
        return $method->getNumberOfParameters() === 0
                        ? $this->{$method->getName()}()
                        : Closure::fromCallable([$this, $method->getName()]);
    }

    /**
     * Determine if the given property / method should be ignored.
     *
     * @param  string  $name
     * @return bool
     */
    protected function shouldIgnore($name)
    {
        return Str::startsWith($name, '__') ||
               in_array($name, $this->ignoredMethods());
    }

    /**
     * Get the methods that should be ignored.
     *
     * @return array
     */
    protected function ignoredMethods()
    {
        return array_merge([
            'data',
            'render',
            'resolveView',
            'shouldRender',
            'view',
            'withAttributes',
        ], $this->except);
    }

    /**
     * Set the extra attributes that the component should make available.
     *
     * @param  array  $attributes
     * @return $this
     */
    public function withAttributes(array $attributes)
    {
        $this->attributes = $this->attributes ?: new ComponentAttributeBag;

        $this->attributes->setAttributes($attributes);

        return $this;
    }

    /**
     * Determine if the component should be rendered.
     *
     * @return bool
     */
    public function shouldRender()
    {
        return true;
    }

    /**
     * Get the non-ignored names of the given methods.
     *
     * @param  array  $reflections
     * @return string[]
     */
    protected function getNames(array $reflections)
    {
        return collect($reflections)->map(function ($reflection) {
            return $reflection->getName();
        })->reject(function ($item) {
            return $this->shouldIgnore($item);
        })->all();
    }

    /**
     * @param  array  $items
     * @param  \Closure  $callable
     *
     * @return array
     */
    protected function collectAttributes($items, Closure $callable)
    {
        $values = [];

        foreach ($items as $name) {
            $values[$name] = $callable($name);
        }

        return $values;
    }
}
