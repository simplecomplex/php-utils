## (PHP) Utils ##

### Main features ###

#### Dependency injection container abstraction ####

As [Wikipedia](https://en.wikipedia.org/wiki/Dependency_injection#Disadvantages) sums it up:
> Ironically, dependency injection can encourage dependence on a dependency injection framework.

**``` Dependency ```** is a simple tool for mitigating dependency of a particular injection container.  
Wraps a [PSR-11](https://github.com/container-interop/fig-standards/blob/container-configuration/proposed/container.md)
or [Pimple](http://pimple.sensiolabs.org) container or creates it's own lightweight PSR-11 container.

#### PHP CLI (command line/console) made easy ####

**``` CliCommand ```** and **``` CliCommandInterface ```**
specify a simple way of defining a CLI command, and auto-generate --help output.

**``` CliEnvironment ```** 

- lists --help of all defined commands
- resolves CLI input arguments and options
- maps to a ``` CliCommand ``` and executes it
- finds document root

#### Odds and ends ####

**``` Unicode ```** abstracts mbstring.

**``` Sanitize ```** delivers basic string sanitizers and converters.

**``` PathFileList ```** hides the complexity of using FilesystemIterators.

**``` Utils ```** parses ini strings/files, and delivers a range of other handy methods. 

### Requirements ###

- PHP >=7.0
- [PSR-11 Container](https://github.com/php-fig/container)
- [PSR-3 Log](https://github.com/php-fig/log)

#### Suggestions ####

- PHP mbstring extension
- PHP intl extension
- [SimpleComplex Inspect](https://github.com/simplecomplex/inspect) (for CLI)
