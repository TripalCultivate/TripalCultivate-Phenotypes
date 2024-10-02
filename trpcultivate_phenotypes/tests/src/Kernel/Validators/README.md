# Validator Tests

Each validator plugin is expected to have its own test file with the following
naming scheme:

`Validator<Name of Validator>Test.php`

# Validator Trait Tests

Tests for Validator Traits go into the `Traits/` folder. They should be named as
follows:

`ValidatorTrait<Name of Trait>Test.php`

Each Validator Trait test class also needs a Fake Validator class to instantiate
within its setUp() method. This allows for tests to be independent of any actual
validators. The class for a fake validator is to be placed within the
`FakeValidators/` folder. The naming scheme is:

`Validator<NameofValidatorTrait>.php`

Occasionally, a validator trait may need multiple different setups to accurately
test error cases. In this case, append a short identifier of the unique setup
in a separate test file. For example:
- `ValidatorGenusConfiguredNOConnection.php`
- `ValidatorGenusConfiguredNOService.php`

# Testing the ValidatorBase class
There is already a `BasicallyBase.php` class in the `FakeValidators/` folder
which can be utilized for testing methods in the ValidatorBase class directly,
therefore elimimating the need to instantiate an actual validator for testing.

Current tests for ValidatorBase are in: `ValidatorBaseTest.php`
