<?php

declare(strict_types=1);

arch('php')->preset()->php();
arch('security')->preset()->security();

arch('every file declares strict types')
    ->expect('App')
    ->toUseStrictTypes()
    ->ignoring('App\Kernel');

arch('classes are final')
    ->expect('App')
    ->classes()
    ->toBeFinal()
    ->ignoring('App\Kernel');

arch('controllers are thin HTTP adapters')
    ->expect('App\Controller')
    ->toHaveSuffix('Controller')
    ->toExtend(Symfony\Bundle\FrameworkBundle\Controller\AbstractController::class)
    ->not->toUse(['App\Repository', 'App\Database', 'App\Model']);

arch('controllers are only reached through routing')
    ->expect('App\Controller')
    ->toOnlyBeUsedIn('App\Controller');

arch('services depend on repository interfaces, not the PDO implementations')
    ->expect(['App\Repository\PdoCustomerRepository', 'App\Repository\PdoTransactionRepository'])
    ->toOnlyBeUsedIn('App\Repository');

arch('repository contracts are interfaces')
    ->expect(['App\Repository\CustomerRepository', 'App\Repository\TransactionRepository'])
    ->toBeInterfaces();

arch('PDO repositories implement their contract')
    ->expect('App\Repository\PdoCustomerRepository')
    ->toImplement('App\Repository\CustomerRepository')
    ->and('App\Repository\PdoTransactionRepository')
    ->toImplement('App\Repository\TransactionRepository');

arch('raw database access stays in the persistence layer')
    ->expect('App\Database\Connection')
    ->toOnlyBeUsedIn(['App\Database', 'App\Repository']);

arch('transactions are opened by services')
    ->expect('App\Database\TransactionManager')
    ->toOnlyBeUsedIn('App\Service');

arch('models are immutable and persistence-agnostic')
    ->expect('App\Model')
    ->toBeReadonly()
    ->not->toUse(['App\Repository', 'App\Database', 'App\Service', 'App\Request', 'App\Response', 'PDO']);

arch('value objects depend on nothing else in the app')
    ->expect('App\ValueObject')
    ->not->toUse([
        'App\Controller',
        'App\Database',
        'App\EventListener',
        'App\Exception',
        'App\Model',
        'App\Repository',
        'App\Request',
        'App\Response',
        'App\Service',
    ]);

arch('domain exceptions are runtime exceptions')
    ->expect('App\Exception')
    ->toExtend(RuntimeException::class);

arch('request DTOs are only consumed by controllers and services')
    ->expect('App\Request')
    ->toHaveSuffix('Request')
    ->toOnlyBeUsedIn(['App\Controller', 'App\Service']);

arch('response DTOs are read-only and only built by controllers')
    ->expect('App\Response')
    ->toHaveSuffix('Response')
    ->toBeReadonly()
    ->toOnlyBeUsedIn(['App\Controller', 'App\Response']);
