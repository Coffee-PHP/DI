<?php

/**
 * Binding.php
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

namespace CoffeePhp\Di\Data;

/**
 * Class Binding
 * @package coffeephp\di
 * @since 2020-07-25
 * @author Danny Damsky <dannydamsky99@gmail.com>
 */
final class Binding
{
    /**
     * Binding constructor.
     * @param string $implementation
     * @param array|null $extraArguments
     * @param object|null $instance
     */
    public function __construct(
        private string $implementation,
        private ?array $extraArguments = null,
        private ?object $instance = null
    ) {
    }

    /**
     * Retrieve the name of the implementation identifier.
     *
     * @return string
     */
    public function getImplementation(): string
    {
        return $this->implementation;
    }

    /**
     * Retrieve the extra arguments used to initialize
     * the implementation.
     *
     * @return array|null
     */
    public function getExtraArguments(): ?array
    {
        return $this->extraArguments;
    }

    /**
     * Return the instance of the implementation.
     *
     * @return object|null
     */
    public function getInstance(): ?object
    {
        return $this->instance;
    }

    /**
     * Get whether instance is set for the
     * implementation.
     *
     * @return bool
     */
    public function hasInstance(): bool
    {
        return $this->instance !== null;
    }

    /**
     * Set the instance of the implementation.
     *
     * @param object|null $instance
     */
    public function setInstance(?object $instance): void
    {
        $this->instance = $instance;
    }
}
