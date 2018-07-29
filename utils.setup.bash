#!/bin/bash -e
# Stop (don't exit) on error.
#-------------------------------------------------------------------------------
### Library: simplecomplex/utils

## PLACE YOURSELF IN THE SITE'S DOCUMENT ROOT.
#cd [document root]

# Set document root var.
doc_root=`pwd`


### CLI command providers ##############
# Register this package's providers;
# PSR-4 path to CliCommandInterface implementation, by command provider alias.
# echo 'command-provider-alias = \VendorName\PackageName\CliPackageName' >> ${doc_root}'/.utils_cli_command_providers.ini'
echo 'utils = \SimpleComplex\Utils\CliUtils' >> ${doc_root}'/.utils_cli_command_providers.ini'


### Success ############################
echo -e "\033[01;32m[success]\033[0m"' SimpleComplex Utils setup succeeded.'
