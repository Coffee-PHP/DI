<?php

/**
 * ContainerBindingsTrait.php
 *
 * Copyright 2021 Danny Damsky
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
 * @since 2021-02-25
 */

declare(strict_types=1);

namespace CoffeePhp\Di\Composition;

use CoffeePhp\Di\Contract\ContainerInterface;
use CoffeePhp\Di\Data\Binding;
use Psr\Container\ContainerInterface as PsrContainerInterface;

/**
 * Trait ContainerBindingsTrait
 * @package coffeephp\di
 * @since 2021-02-25
 * @author Danny Damsky <dannydamsky99@gmail.com>
 */
trait ContainerBindingsTrait
{
    /**
     * Constructor for the bindings aware container implementation.
     * @param array<string, Binding> $bindings
     */
    public function __construct(private array $bindings = [])
    {
        $binding = new Binding(static::class, null, $this);
        $this->bindings[static::class] = $binding;
        $this->bindings[ContainerInterface::class] = $binding;
        $this->bindings[PsrContainerInterface::class] = $binding;
    }

    /**
     * Get the collection of currently configured bindings.
     *
     * @return array<string, Binding>
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }

    /**
     * Get whether the current identifier is configured in the container.
     *
     * @param string $id
     * @return bool
     * @noinspection PhpMissingParamTypeInspection
     */
    public function has($id): bool
    {
        return isset($this->bindings[$id]);
    }

    /**
     * Bind the given implementation to the identifier.
     *
     * @param string $id The identifier to use for the binding.
     * @param string $implementation The implementation to bind to the identifier.
     * @param array|null $extraArguments A map of constructor argument names as keys and argument values as values.
     */
    public function bind(string $id, string $implementation, ?array $extraArguments = null): void
    {
        $this->bindings[$id] = new Binding($implementation, $extraArguments);
    }
}
