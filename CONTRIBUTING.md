# Contributing

Contributions are welcome.

You can contribute in multiple ways, for example:
- Open issues to report bugs, make feature requests or ask questions.
- Create pull requests to propose changes. For extensive changes, please propose change as issue first.
- Make changes to the documentation, either by a pull request or by clicking on "Edit on GitHub" in
the top right of a documentation page.

##Development
A similar testing workflow to the TYPO3 core is used. However, it
is currently not as extensive as the core, but the following is
checked:

Coding guidelines (CGL)
functional tests
For more information, see the file .github/workflows/qc-references-workflow.yaml and
the documentation on https://docs.typo3.org/m/typo3/reference-coreapi/10.4/en-us/Testing/ExtensionTesting.html.

###CGL
- To create the .Build directory and create the composer.lock, run this command :

    `` Build/Scripts/runTests.sh -p 7.4 -s composerInstallMax ``



- Check and fix CGL in PHP files : 

  ``Build/Scripts/runTests.sh -s cgl -v``


