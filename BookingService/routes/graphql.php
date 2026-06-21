<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Nuwave\Lighthouse\Http\GraphQLController;

/*
|----------------------------------------------------------------------
| GraphQL endpoint — Lighthouse (manual route untuk kompatibilitas Laravel 12)
| Lighthouse auto-route dinonaktifkan di config/lighthouse.php.
| Route ini mendelegasikan ke GraphQLController melalui container
| agar dependency injection method-level berjalan dengan benar.
| File ini didaftarkan tanpa middleware group di bootstrap/app.php.
|----------------------------------------------------------------------
*/
Route::match(['GET', 'POST'], '/graphql', function (Request $request) {
    $controller = app(GraphQLController::class);
    return $controller->__invoke(
        $request,
        app(\Nuwave\Lighthouse\GraphQL::class),
        app(\Illuminate\Contracts\Events\Dispatcher::class),
        app(\Laragraph\Utils\RequestParser::class),
        app(\Nuwave\Lighthouse\Support\Contracts\CreatesResponse::class),
        app(\Nuwave\Lighthouse\Support\Contracts\CreatesContext::class),
    );
});
