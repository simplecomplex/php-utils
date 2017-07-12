## (PHP) Utils ##

#### Requirements ####

- PHP >=7.0
- [PSR-11 Container](https://github.com/php-fig/container)
- [PSR-3 Log](https://github.com/php-fig/log)

##### Suggestions #####

- PHP mbstring extension
- PHP intl extension
- [SimpleComplex Inspect](https://github.com/simplecomplex/inspect) (for CLI)

### Main features ###

#### Dependency injection container abstraction ####

As [Wikipedia](https://en.wikipedia.org/wiki/Dependency_injection#Disadvantages) sums it up:
> Ironically, dependency injection can encourage dependence on a dependency injection framework.

**``` Dependency ```** is a simple tool for mitigating dependency of a particular injection container.

Wraps a [PSR-11](https://github.com/container-interop/fig-standards/blob/container-configuration/proposed/container.md)
or [Pimple](http://pimple.sensiolabs.org) container or creates it's own lightweight PSR-11 container.

#### PHP CLI/console made easy ####

**``` CliCommand ```**
specifies a simple way of declaring a CLI command

``` CliEnvironment ``` 


The gem of this package is the command line utilities.
