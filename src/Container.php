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

declare(strict_types=1);

namespace CoffeePhp\Di;

use CoffeePhp\Di\Contract\ContainerInterface;
use CoffeePhp\Di\Data\Binding;
use CoffeePhp\Di\Exception\DiException;
use ReflectionClass;
use ReflectionException;
use ReflectionParameter;
use Throwable;

use function is_string;

/**
 * Class Container
 * @package coffeephp\di
 * @since 2020-07-25
 * @author Danny Damsky <dannydamsky99@gmail.com>
 */
class Container extends AbstractContainer
{
    /**
     * Container constructor.
     * @param array<string, Binding> $bindings
     */
    final public function __construct(private array $bindings = [])
    {
        $binding = new Binding(static::class, null, $this);
        $this->bindings[static::class] = $binding;
        $this->bindings[ContainerInterface::class] = $binding;
    }

    /**
     * @inheritDoc
     */
    final protected function getInstance(string $identifier): object
    {
        if (isset($this->bindings[$identifier])) {
            return $this->createClassFromBinding($this->bindings[$identifier]);
        }
        $instance = $this->create($identifier);
        $this->bindings[$identifier] = new Binding($identifier, null, $instance);
        return $instance;
    }

    /**
     * @param Binding $binding
     * @return object
     */
    private function createClassFromBinding(Binding $binding): object
    {
        $instance = $binding->getInstance();
        if ($instance !== null) {
            return $instance;
        }
        $innerBinding = $this->getFirstBindingWithInstance($binding);
        $instance = $innerBinding->getInstance()
            ?? $this->create($innerBinding->getImplementation(), $innerBinding->getExtraArguments());
        $this->setInstanceToAllBindings($binding, $instance);
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
    final protected function hasInstance(string $identifier): bool
    {
        return isset($this->bindings[$identifier]);
    }

    /**
     * @inheritDoc
     */
    final public function bind(string $identifier, string $implementation, ?array $extraArguments = null): void
    {
        $this->bindings[$identifier] = new Binding($implementation, $extraArguments);
    }

    /**
     * @inheritDoc
     */
    final public function create(string $implementation, ?array $extraArguments = null): object
    {
        try {
            return $this->initialize($implementation, $extraArguments);
        } catch (DiException $e) {
            throw new DiException(
                "{$e->getMessage()}; Implementation: $implementation",
                (int)$e->getCode(),
                $e
            );
        } catch (ReflectionException $e) {
            throw new DiException(
                "Reflection Error: {$e->getMessage()} ; Implementation: $implementation",
                (int)$e->getCode(),
                $e
            );
        } catch (Throwable $e) {
            throw new DiException(
                "Unknown Error: {$e->getMessage()} ; Implementation: $implementation",
                (int)$e->getCode(),
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
        if ($class->isAbstract() && !$class->isInstantiable()) {
            $class = $this->onNonInstantiableImplementationFound($class);
        }
        return $class;
    }

    /**
     * React on a non-instantiable class implementation.
     *
     * @param ReflectionClass $class
     * @return ReflectionClass
     * @throws ReflectionException
     */
    protected function onNonInstantiableImplementationFound(ReflectionClass $class): ReflectionClass
    {
        // By default a non-instantiable class will cause an exception to be thrown.
        throw new ReflectionException("Could not find implementation for abstraction: {$class->getName()}");
    }

    /**
     * @param ReflectionParameter $parameter
     * @param array|null $extraArguments
     * @return mixed
     * @throws ReflectionException
     */
    private function initializeParameter(ReflectionParameter $parameter, ?array $extraArguments): mixed
    {
        $exceptionCode = 0;
        $previousException = null;
        $parameterName = $parameter->getName();
        $parameterClass = $parameter->getType();

        if (isset($extraArguments[$parameterName])) {
            /** @var mixed|string $argument */
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
                $exceptionCode = (int)$e->getCode();
                $previousException = $e;
            }
        }

        if ($parameter->isOptional()) {
            return $parameter->getDefaultValue();
        }

        if ($parameter->getType()?->allowsNull()) {
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
    final public function getBindings(): array
    {
        return $this->bindings;
    }
}
