<?php

/**
 * Container.php
 *
 * Copyright 2020 Danny Damsky
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *    http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * @package coffeephp\di
 * @author Danny Damsky <dannydamsky99@gmail.com>
 * @since 2020-07-25
 */

declare (strict_types=1);

namespace CoffeePhp\Di;


use CoffeePhp\Di\Contract\ContainerInterface;
use CoffeePhp\Di\Data\Binding;
use CoffeePhp\Di\Exception\DiException;
use ReflectionClass;
use ReflectionException;
use ReflectionParameter;
use Throwable;

use function class_exists;
use function get_declared_classes;
use function is_string;
use function similar_text;
use function strpos;

/**
 * Class Container
 * @package coffeephp\di
 * @since 2020-07-25
 * @author Danny Damsky <dannydamsky99@gmail.com>
 */
final class Container extends AbstractContainer
{
    /**
     * @var Binding[]
     */
    private array $bindings;

    private bool $composerClassesAutoloaded = false;

    /**
     * Container constructor.
     * @param Binding[] $bindings
     */
    public function __construct(array $bindings = [])
    {
        $this->bindings = $bindings;
        $binding = new Binding(self::class, null, $this);
        $this->bindings[self::class] = $binding;
        $this->bindings[ContainerInterface::class] = $binding;
    }

    /**
     * @inheritDoc
     */
    protected function getInstance(string $identifier): object
    {
        if (isset($this->bindings[$identifier])) {
            $binding = $this->bindings[$identifier];
            if (($instance = $binding->getInstance()) !== null) {
                return $instance;
            }
            $innerBinding = $this->getFirstBindingWithInstance($binding);
            if (($instance = $innerBinding->getInstance()) === null) {
                $instance = $this->create($innerBinding->getImplementation(), $innerBinding->getExtraArguments());
            }
            $this->setInstanceToAllBindings($binding, $instance);
            return $instance;
        }
        $instance = $this->create($identifier);
        $this->bindings[$identifier] = new Binding($identifier, null, $instance);
        return $instance;
    }

    /**
     * @param Binding $binding
     * @param object $instance
     */
    private function setInstanceToAllBindings(Binding $binding, object $instance): void
    {
        $binding->setInstance($instance);
        $implementation = $binding->getImplementation();
        while (isset($this->bindings[$implementation])) {
            $nextBinding = $this->bindings[$implementation];
            if ($nextBinding->getExtraArguments() !== $binding->getExtraArguments()) {
                break;
            }
            $binding = $nextBinding;
            $binding->setInstance($instance);
            $implementation = $binding->getImplementation();
            if ($binding === ($this->bindings[$implementation] ?? $binding)) {
                break;
            }
        }
    }

    /**
     * Get the first binding that has an instantiated
     * object inside it.
     *
     * If no instances found, the last binding found will
     * be returned.
     *
     * @param Binding $binding
     * @return Binding
     */
    private function getFirstBindingWithInstance(Binding $binding): Binding
    {
        $implementation = $binding->getImplementation();
        while (isset($this->bindings[$implementation])) {
            $nextBinding = $this->bindings[$implementation];
            if ($nextBinding->getExtraArguments() !== $binding->getExtraArguments()) {
                break;
            }
            $binding = $nextBinding;
            if ($binding->hasInstance()) {
                return $binding;
            }
            $implementation = $binding->getImplementation();
            if ($binding === ($this->bindings[$implementation] ?? $binding)) {
                break;
            }
        }
        return $binding;
    }

    /**
     * @inheritDoc
     */
    protected function hasInstance(string $identifier): bool
    {
        return isset($this->bindings[$identifier]);
    }

    /**
     * @inheritDoc
     */
    public function bind(string $identifier, string $implementation, ?array $extraArguments = null): void
    {
        $this->bindings[$identifier] = new Binding($implementation, $extraArguments);
    }

    /**
     * @inheritDoc
     */
    public function create(string $implementation, ?array $extraArguments = null): object
    {
        try {
            return $this->initialize($implementation, $extraArguments);
        } catch (DiException $e) {
            throw new DiException(
                "{$e->getMessage()}; Implementation: $implementation",
                $e->getCode(),
                $e
            );
        } catch (ReflectionException $e) {
            throw new DiException(
                "Reflection Error: {$e->getMessage()} ; Implementation: $implementation",
                $e->getCode(),
                $e
            );
        } catch (Throwable $e) {
            throw new DiException(
                "Unknown Error: {$e->getMessage()} ; Implementation: $implementation",
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * @param string $implementation
     * @param array|null $extraArguments
     * @return object
     * @throws ReflectionException
     */
    private function initialize(string $implementation, ?array $extraArguments): object
    {
        $class = $this->getReflectionClassFromImplementation($implementation);
        $args = [];
        $constructor = $class->getConstructor();
        if ($constructor !== null) {
            foreach ($constructor->getParameters() as $parameter) {
                $args[] = $this->initializeParameter($parameter, $extraArguments);
            }
        }
        return $class->newInstance(...$args);
    }

    /**
     * @param string $implementation
     * @return ReflectionClass
     * @throws ReflectionException
     */
    private function getReflectionClassFromImplementation(string $implementation): ReflectionClass
    {
        $class = new ReflectionClass($implementation);
        if (
            $class->isAbstract() &&
            !$class->isInstantiable() &&
            ($class = $this->getReflectionClassFromAbstraction($class)) === null
        ) {
            throw new ReflectionException("Could not find implementation for abstraction: $implementation");
        }
        return $class;
    }

    /**
     * @param ReflectionClass $abstraction
     * @return ReflectionClass|null
     */
    private function getReflectionClassFromAbstraction(ReflectionClass $abstraction): ?ReflectionClass
    {
        $implementation = $abstraction->getName();
        $interfaceName = $abstraction->getShortName();
        $currentSimilarity = -1;
        $class = null;
        foreach (get_declared_classes() as $className) {
            try {
                $classCandidate = new ReflectionClass($className);
                if (
                    $classCandidate->isSubclassOf($implementation) &&
                    $classCandidate->isInstantiable()
                ) {
                    $newSimilarity = (int)similar_text($interfaceName, $classCandidate->getShortName());
                    if (
                        $class === null ||
                        $newSimilarity > $currentSimilarity
                    ) {
                        $currentSimilarity = $newSimilarity;
                        $class = $classCandidate;
                    }
                }
            } catch (ReflectionException $e) {
                // Do nothing.
            }
        }
        if (!$this->composerClassesAutoloaded && $class === null) {
            $this->requireAllComposerClasses();
            $this->composerClassesAutoloaded = true;
            return $this->getReflectionClassFromAbstraction($abstraction);
        }
        return $class;
    }

    private function requireAllComposerClasses(): void
    {
        try {
            foreach (get_declared_classes() as $className) {
                if (strpos($className, 'ComposerAutoloaderInit') === 0) {
                    foreach ($className::getLoader()->getClassMap() as $namespace => $path) {
                        class_exists($namespace, true);
                    }
                    break;
                }
            }
        } catch (Throwable $e) {
            // Do nothing.
        }
    }

    /**
     * @param ReflectionParameter $parameter
     * @param array|null $extraArguments
     * @return mixed
     * @throws ReflectionException
     */
    private function initializeParameter(ReflectionParameter $parameter, ?array $extraArguments)
    {
        $exceptionCode = 0;
        $previousException = null;
        $parameterName = $parameter->getName();
        $parameterClass = $parameter->getClass();

        if ($extraArguments !== null && isset($extraArguments[$parameterName])) {
            $argument = $extraArguments[$parameterName];
            if (
                $parameterClass !== null &&
                is_string($argument) &&
                isset($this->bindings[$argument])
            ) {
                return $this->getInstance($argument);
            }
            return $argument;
        }

        if ($parameterClass !== null) {
            try {
                return $this->getInstance($parameterClass->getName());
            } catch (DiException $e) {
                $exceptionCode = $e->getCode();
                $previousException = $e;
            }
        }

        if ($parameter->isOptional()) {
            return $parameter->getDefaultValue();
        }

        if (($type = $parameter->getType()) !== null && $type->allowsNull()) {
            return null;
        }

        throw new ReflectionException(
            "Could not parse parameter: {$parameter->getName()}",
            $exceptionCode,
            $previousException
        );
    }

    /**
     * @inheritDoc
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }
}
