
# Validator Traits

These classes provide useful methods such as setters that are used among multiple validator instance.

They have the benefit of
1. Reducing code duplication (i.e. no need to define setGenus in multiple validators)
2. Simplify hierarchy (i.e. no need to make parent/base abstract classes for validators with common functionality)
3. Allow a validator to use multiple traits (php only supports a single parent).

Each trait is named based on the requirement / dependency it represents in your validator.
