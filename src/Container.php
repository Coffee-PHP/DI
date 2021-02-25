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

use CoffeePhp\Di\Composition\ContainerBindingsInstanceMutationTrait;
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
    use ContainerBindingsInstanceMutationTrait;

    /**
     * @inheritDoc
     * @noinspection PhpRedundantVariableDocTypeInspection
     */
    final protected function getInstance(string $identifier): object
    {
        if (isset($this->bindings[$identifier])) {
            return $this->createClassFromBinding($this->bindings[$identifier]);
        }
        /** @var class-string $identifier */
        $instance = $this->create($identifier);
        $this->bindings[$identifier] = new Binding($identifier, null, $instance);
        return $instance;
    }

    /**
     * @param Binding $binding
     * @return object
     * @noinspection PhpRedundantVariableDocTypeInspection
     */
    private function createClassFromBinding(Binding $binding): object
    {
        $instance = $binding->getInstance();
        if ($instance !== null) {
            return $instance;
        }
        $innerBinding = $this->getFirstBindingWithInstance($binding);
        $instance = $innerBinding->getInstance();
        if ($instance === null) {
            /** @var class-string $implementation */
            $implementation = $innerBinding->getImplementation();
            $instance = $this->create($implementation, $innerBinding->getExtraArguments());
        }
        $this->setInstanceToAllBindings($binding, $instance);
        return $instance;
    }

    /**
     * @inheritDoc
     */
    final public function create(string $implementation, ?array $extraArguments = null): object
    {
        try {
            return $this->initialize($implementation, $extraArguments);
        } catch (DiException $e) {
            $error = $e;
            $errorType = 'Internal';
        } catch (ReflectionException $e) {
            $error = $e;
            $errorType = 'Reflection';
        } catch (Throwable $e) {
            $error = $e;
            $errorType = 'Unknown';
        }
        throw new DiException(
            "{$errorType} Error: {$error->getMessage()} ; Implementation: $implementation",
            (int)$error->getCode(),
            $error
        );
    }

    /**
     * @param class-string $implementation
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
                /**
                 * This method is supposed to return mixed.
                 * @psalm-suppress MixedAssignment
                 */
                $args[] = $this->initializeParameter($parameter, $extraArguments);
            }
        }
        return $class->newInstance(...$args);
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
            $class = $this->onNonInstantiableImplementationFound($class);
        }
        return $class;
    }

    /**
     * React on a non-instantiable class implementation.
     *
     * @param ReflectionClass<object> $class
     * @return ReflectionClass<object>
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
        $parameterName = $parameter->getName();
        $parameterType = $parameter->getType();

        if (isset($extraArguments[$parameterName])) {
            /** @var mixed|string $argument */
            $argument = $extraArguments[$parameterName];
            if ($parameterType !== null && is_string($argument) && isset($this->bindings[$argument])) {
                return $this->getInstance($argument);
            }
            return $argument;
        }

        $exceptionCode = 0;
        $previousException = null;
        if ($parameterType !== null) {
            try {
                return $this->getInstance((string)$parameterType);
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
}
