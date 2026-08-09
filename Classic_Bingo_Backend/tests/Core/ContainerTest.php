<?php

namespace App\Tests\Core;

use App\Core\Container;
use Exception;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

// Dummy classes for testing purposes
class DummyService {}

class ServiceWithDependency {
    public function __construct(public DummyService $dependency) {}
}


#[CoversClass(Container::class)]
final class ContainerTest extends TestCase {

    #[Test]
    public function it_throws_an_exception_when_resolving_an_unbound_service(): void {
        // Arrange
        $container = new Container();
        $key = 'NonExistentService';

        // Assert
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("No binding found for '{$key}'");

        // Act
        $container->resolve($key);
    }

    #[Test]
    public function it_binds_and_resolves_a_transient_service(): void {
        // Arrange
        $container = new Container();
        $container->bind(DummyService::class, fn() => new DummyService());

        // Act
        $instance1 = $container->resolve(DummyService::class);
        $instance2 = $container->resolve(DummyService::class);

        // Assert
        $this->assertInstanceOf(DummyService::class, $instance1);
        $this->assertInstanceOf(DummyService::class, $instance2);
        // For transient bindings, each resolution should create a new object instance.
        $this->assertNotSame($instance1, $instance2, 'Transient bindings should return different instances.');
    }

    #[Test]
    public function it_binds_and_resolves_a_singleton_service(): void {
        // Arrange
        $container = new Container();
        $container->singleton(DummyService::class, fn() => new DummyService());

        // Act
        $instance1 = $container->resolve(DummyService::class);
        $instance2 = $container->resolve(DummyService::class);

        // Assert
        $this->assertInstanceOf(DummyService::class, $instance1);
        // For singletons, each resolution should return the exact same object instance.
        $this->assertSame($instance1, $instance2, 'Singleton bindings should return the same instance.');
    }

    #[Test]
    public function it_can_resolve_nested_dependencies(): void {
        // Arrange
        $container = new Container();
        
        // The container instance is passed to the resolver, allowing it to resolve other services.
        $container->singleton(DummyService::class, fn() => new DummyService());
        $container->bind(
            ServiceWithDependency::class, 
            fn(Container $c) => new ServiceWithDependency($c->resolve(DummyService::class))
        );

        // Act
        $service = $container->resolve(ServiceWithDependency::class);

        // Assert
        $this->assertInstanceOf(ServiceWithDependency::class, $service);
        $this->assertInstanceOf(DummyService::class, $service->dependency);
    }
    
    #[Test]
    public function it_reuses_the_singleton_instance_for_nested_dependencies(): void {
        // Arrange
        $container = new Container();
        
        $container->singleton(DummyService::class, fn() => new DummyService());
        $container->singleton(
            ServiceWithDependency::class, 
            fn(Container $c) => new ServiceWithDependency($c->resolve(DummyService::class))
        );

        // Act
        // Resolve the dependency directly first
        $dummyServiceInstance = $container->resolve(DummyService::class);
        // Then resolve the service that depends on it
        $mainServiceInstance = $container->resolve(ServiceWithDependency::class);

        // Assert
        // The dependency injected into the main service should be the exact same instance
        // as the one we resolved directly, because it was bound as a singleton.
        $this->assertSame(
            $dummyServiceInstance, 
            $mainServiceInstance->dependency,
            'The nested singleton dependency should be the same instance.'
        );
    }
}
