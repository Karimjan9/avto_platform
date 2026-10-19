<?php

namespace App\Services;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;

class PayloadValidator
{
    public function validate(string $requestClass, Request $parent, array $data, array $bindings = []): array
    {
        /** @var FormRequest $child */
        $child = $requestClass::create('/internal-validation', 'POST', $data);
        $route = new Route('POST', 'internal-validation', fn () => null);
        $route->bind($child);
        foreach ($bindings as $key => $value) {
            $route->setParameter($key, $value);
        }
        $child->setContainer(app())->setRedirector(app('redirect'));
        $child->setUserResolver(fn () => $parent->user());
        $child->setRouteResolver(fn () => $route);
        $child->validateResolved();

        return $child->validated();
    }
}
