<?php

/**
 * ContainerBindingsInstanceIterationTrait.php
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

use CoffeePhp\Di\Data\Binding;

/**
 * Trait ContainerBindingsInstanceIterationTrait
 * @package coffeephp\di
 * @since 2021-02-25
 * @author Danny Damsky <dannydamsky99@gmail.com>
 */
trait ContainerBindingsInstanceIterationTrait
{
    use ContainerBindingsTrait;

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
}
