# Contributing

Contributions are welcome.

You can contribute in multiple ways, for example:
- Open issues to report bugs, make feature requests or ask questions.
- Create pull requests to propose changes. For extensive changes, please propose change as issue first.
- Make changes to the documentation, either by a pull request.
##Development
A similar testing workflow to the TYPO3 core is used. However, it
is currently not as extensive as the core, but the following is
checked:

- Coding guidelines (CGL)
- Functional tests

For more information, see the file .github/workflows/qc-references-workflow.yaml and
the documentation on :

https://docs.typo3.org/m/typo3/reference-coreapi/10.4/en-us/Testing/ExtensionTesting.html.

To create the .Build directory and create the composer.lock, run the command bellow :

``Build/Scripts/runTests.sh -s composerInstallMax``

###CGL
- Check and fix CGL in PHP files :

``Build/Scripts/runTests.sh -s cgl``

###Functinal test
- In order to run the functional test of the extension, you can run the command line :


``Build/Scripts/runTests.sh -s functional``


