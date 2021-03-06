## (PHP) Utils ##

### Main features ###

#### Dependency injection container abstraction ####

As [Wikipedia](https://en.wikipedia.org/wiki/Dependency_injection#Disadvantages) sums it up:
> Ironically, dependency injection can encourage dependence on a dependency injection framework.

**``` Dependency ```** is a simple tool for mitigating dependency of a particular injection container.  
Wraps a [PSR-11](https://github.com/container-interop/fig-standards/blob/container-configuration/proposed/container.md)
or [Pimple](http://pimple.sensiolabs.org) container or creates it's own lightweight PSR-11 container.

#### PHP CLI (command line/console) made easy ####

**``` CliCommand ```** + **``` CliCommandInterface ```**  
specify a simple way of defining PHP CLI commands, and auto-generate --help output.

**``` CliEnvironment ```** 

- resolves CLI input arguments and options
- maps to a ``` CliCommand ``` and executes it
- lists --help of all defined commands
- finds document root

##### Utils' own CLI commands #####

```bash
# List all commands in the system, and their providers.
php cli.php -h

# One command's help.
php cli.php utils-xxx -h

# (RISKY) Execute included PHP script.
php cli.php utils-execute include-file
```

#### Time ####

**``` Time ```** extends the native DateTime class to fix shortcomings and defects,  
and provide more, simpler and safer getters and setters.

Features:
 * is stringable (sic!), to ISO-8601
 * JSON serializes to string ISO-8601 with timezone marker
 * freezable
 * enhanced timezone awareness
 * diff (diffConstant, that is) works correctly across differing timezones
 * simpler and safer getters and setters
 
It's inspired by Javascript's Date class, and secures better Javascript interoperability  
by stresssing and facilitating timezone awareness,  and by JSON serializing to ISO-8601 timestamp string;  
_not_ a phoney Javascript object representing a PHP DateTime's inner properties. 

#### Explorable ####

The abstract **``` Explorable ```** class provides simple means for making protected members of an object readable,  
and optionally mutable via dedicated methods.

#### Odds and ends ####

**``` Unicode ```** abstracts mbstring.

**``` Sanitize ```** delivers basic string sanitizers and converters.

**``` PathList ```** hides the complexity of using FilesystemIterators.

**``` Utils ```** parses ini strings/files, and delivers a range of other handy methods.

**``` Bootstrap::prepareDependencies() ```** for easy dependency setup.  
<sub>NB: Bootstrap::prepareDependencies() requires packages not listed among composer requirements:  
[Cache](https://github.com/simplecomplex/php-cache),
[Config](https://github.com/simplecomplex/php-config),
(optional) [JsonLog](https://github.com/simplecomplex/php-jsonlog),
[Inspect](https://github.com/simplecomplex/inspect),
[Locale](https://github.com/simplecomplex/php-locale),
[Validate](https://github.com/simplecomplex/php-validate).
</sub>

### Requirements ###

- PHP >=7.0
- [PSR-11 Container](https://github.com/php-fig/container)
- [PSR-3 Log](https://github.com/php-fig/log)

#### Development requirements ####
- [PHPUnit](https://github.com/sebastianbergmann/phpunit)

#### Suggestions ####
- PHP mbstring extension<!-- - PHP intl extension -->
- [SimpleComplex Inspect](https://github.com/simplecomplex/inspect)
