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
use CoffeePhp\Di\Exception\DiBindingNotFoundException;
use CoffeePhp\Di\Exception\DiException;
use Psr\Container\ContainerInterface as PsrContainerInterface;
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
final class Container implements ContainerInterface
{
    /**
     * @inheritDoc
     */
    public function __construct(private array $bindings = [])
    {
        $binding = new Binding(self::class, null, $this);
        $this->bindings[self::class] = $binding;
        $this->bindings[ContainerInterface::class] = $binding;
        $this->bindings[PsrContainerInterface::class] = $binding;
    }

    /**
     * @inheritDoc
     */
    public function get(string $id): object
    {
        if (isset($this->bindings[$id])) {
            return $this->resolveInstanceFromBinding($this->bindings[$id]);
        }
        $binding = new Binding($id);
        $this->processBinding($binding);
        $this->bindings[$id] = $binding;
        return $binding->getInstance();
    }

    /**
     * @param Binding $binding
     * @return object
     */
    private function resolveInstanceFromBinding(Binding $binding): object
    {
        $instance = $binding->getInstance();
        if ($instance !== null) {
            return $instance;
        }
        $innerBinding = $this->getFirstBindingWithInstance($binding);
        if ($innerBinding->getInstance() === null) {
            $this->processBinding($innerBinding);
        }
        $instance = $innerBinding->getInstance();
        $this->setInstanceToAllBindings($binding, $instance);
        return $instance;
    }

    /**
     * @param Binding $binding
     * @param object $instance
     * @noinspection SuspiciousLoopInspection
     */
    private function setInstanceToAllBindings(Binding $binding, object $instance): void
    {
        $binding->setInstance($instance);
        foreach ($this->getBindingInstancesIterator($binding) as $binding) {
            $binding->setInstance($instance);
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
     * @noinspection SuspiciousLoopInspection
     */
    private function getFirstBindingWithInstance(Binding $binding): Binding
    {
        foreach ($this->getBindingInstancesIterator($binding) as $binding) {
            if ($binding->hasInstance()) {
                break;
            }
        }
        return $binding;
    }

    /**
     * Get an iterator of binding instances for the given binding.
     *
     * @param Binding $binding
     * @return iterable<int, Binding>
     */
    private function getBindingInstancesIterator(Binding $binding): iterable
    {
        $implementation = $binding->getImplementation();
        while (isset($this->bindings[$implementation])) {
            $nextBinding = $this->bindings[$implementation];
            if ($nextBinding->getExtraArguments() !== $binding->getExtraArguments()) {
                break;
            }
            $binding = $nextBinding;
            yield $binding;
            $implementation = $binding->getImplementation();
            if ($binding === ($this->bindings[$implementation] ?? $binding)) {
                break;
            }
        }
    }

    /**
     * Create an instance of a class configured for the given identifier
     * and set it to the provided binding.
     *
     * @param Binding $binding
     * @throws DiBindingNotFoundException
     * @throws DiException
     */
    private function processBinding(Binding $binding): void
    {
        try {
            $this->initializeBinding($binding);
        } catch (DiBindingNotFoundException $e) {
            throw new DiBindingNotFoundException(
                "Internal Error: {$e->getMessage()} ; Implementation: {$binding->getImplementation()}",
                (int)$e->getCode(),
                $e
            );
        } catch (DiException $e) {
            throw new DiException(
                "Internal Error: {$e->getMessage()} ; Implementation: {$binding->getImplementation()}",
                (int)$e->getCode(),
                $e
            );
        } catch (ReflectionException $e) {
            throw new DiException(
                "Reflection Error: {$e->getMessage()} ; Implementation: {$binding->getImplementation()}",
                (int)$e->getCode(),
                $e
            );
        } catch (Throwable $e) {
            throw new DiException(
                "Unknown Error: {$e->getMessage()} ; Implementation: {$binding->getImplementation()}",
                (int)$e->getCode(),
                $e
            );
        }
    }

    /**
     * @param Binding $binding
     * @return void
     * @throws ReflectionException
     */
    private function initializeBinding(Binding $binding): void
    {
        $class = $this->getReflectionClassFromImplementation($binding->getImplementation());
        $args = [];
        $constructor = $class->getConstructor();
        if ($constructor !== null) {
            $extraArguments = $binding->getExtraArguments();
            foreach ($constructor->getParameters() as $parameter) {
                $args[] = $this->initializeParameter($parameter, $extraArguments);
            }
        }
        $binding->setInstance($class->newInstance(...$args));
    }

    /**
     * @param class-string $implementation
     * @return ReflectionClass<object>
     * @throws ReflectionException
     */
    private function getReflectionClassFromImplementation(string $implementation): ReflectionClass
    {
        $class = new ReflectionClass($implementation);
        if ($class->isAbstract() && !$class->isInstantiable()) {
            throw new DiBindingNotFoundException("Could not find implementation for abstraction: {$class->getName()}");
        }
        return $class;
    }

    /**
     * @param ReflectionParameter $parameter
     * @param array|null $extraArguments
     * @return mixed
     * @throws ReflectionException
     */
    private function initializeParameter(ReflectionParameter $parameter, ?array $extraArguments): mixed
    {
        $parameterName = $parameter->getName();
        $parameterType = $parameter->getType();

        if (isset($extraArguments[$parameterName])) {
            $argument = $extraArguments[$parameterName];
            if ($parameterType !== null && is_string($argument) && isset($this->bindings[$argument])) {
                return $this->get($argument);
            }
            return $argument;
        }

        $previousMessage = '';
        $previousExceptionCode = 0;
        $previousException = null;
        if ($parameterType !== null) {
            try {
                $parameterTypeClass = (string)$parameterType;

                if (isset($extraArguments[$parameterTypeClass])) {
                    $argument = $extraArguments[$parameterTypeClass];
                    if (is_string($argument) && isset($this->bindings[$argument])) {
                        return $this->get($argument);
                    }
                    return $argument;
                }

                return $this->get($parameterTypeClass);
            } catch (DiException $e) {
                $previousMessage = $e->getMessage();
                $previousExceptionCode = (int)$e->getCode();
                $previousException = $e;
            }
        }

        if ($parameter->isOptional()) {
            return $parameter->getDefaultValue();
        }

        if ($parameterType?->allowsNull()) {
            return null;
        }

        throw new DiException(
            "Could not parse parameter: {$parameter->getName()} ; {$previousMessage}",
            $previousExceptionCode,
            $previousException
        );
    }

    /**
     * @inheritDoc
     */
    public function has(string $id): bool
    {
        return isset($this->bindings[$id]);
    }

    /**
     * @inheritDoc
     */
    public function bind(string $id, string $implementation, ?array $extraArguments = null): void
    {
        $this->bindings[$id] = new Binding($implementation, $extraArguments);
    }

    /**
     * @inheritDoc
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }
}
