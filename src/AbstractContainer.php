<?php

/**
 * AbstractContainer.php
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
 * @since 2020-07-15
 */

declare (strict_types=1);

namespace CoffeePhp\Di;


use CoffeePhp\Di\Contract\ContainerInterface;
use CoffeePhp\Di\Exception\DiBindingNotFoundException;
use CoffeePhp\Di\Exception\DiException;

/**
 * Class AbstractContainer
 * @package coffeephp\di
 * @since 2020-07-15
 * @author Danny Damsky <dannydamsky99@gmail.com>
 */
abstract class AbstractContainer implements ContainerInterface
{

    /**
     * @inheritDoc
     */
    final public function get($identifier): object
    {
        return $this->getInstance($identifier);
    }

    /**
     * Retrieve the shared instance of a class configured for the given identifier.
     *
     * @param string $identifier
     * @return object
     * @throws DiBindingNotFoundException
     * @throws DiException
     */
    abstract protected function getInstance(string $identifier): object;

    /**
     * @inheritDoc
     */
    final public function has($identifier): bool
    {
        return $this->hasInstance($identifier);
    }

    /**
     * Get whether the current identifier is configured in the container.
     *
     * @param string $identifier
     * @return bool
     */
    abstract protected function hasInstance(string $identifier): bool;

}
