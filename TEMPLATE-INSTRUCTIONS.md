# Template Instructions

This document describes the changes you should make when using this template to customize it for your specific module.

## Create a new repo using this template

Create a new repository using this one as the template by clicking the green "Use this template" button at the top of this repository and then selecting "Create a new repository" frorm the drop-down. Fill in the details ensuring it is in the correct organization and then click "create repository from template". This will give you a copy of this repository with the basic details you provided.

![use-this-template](https://user-images.githubusercontent.com/1566301/222283653-e2aadd7c-7c9e-4144-9810-2683a6ce1757.png)

Modify the Readme template by replacing all the SCREAMING words to match your project and updating the reference links at the bottom to match your repository.

## Sign your repository up for Zenodo

Sign into [Zenodo](https://zenodo.org) using Github (remember not to use your own account) and go to https://zenodo.org/account/settings/github/. Follow the instructions on this page to turn on automatic preservation of your software. This will create a DOI when you eventually release an official version that can be used in a more official citation.

![zenodo-instructions](https://user-images.githubusercontent.com/1566301/222283278-39546c13-ea29-4882-b1a8-5c37243d9e0b.png)

## Setup Code Climate automated testing

Sign into [Code Climate](https://codeclimate.com/login) using Github (remember not to use your own account). Click on "Add repository" within the open source section and then select your new templated repo. You may need to use the sync button if it is not already in the list. This will then create a new build of your repo on code climate.

Next, you can get the badge information by going to "Repo Settings" in the top menu and then clicking on "Badges" under the "Extra" section in the left sidebar. The choose restructured text for both the Maintainability and Code Coverage Badges. Save this information for the next step.


![codeclimate-badge-info](https://user-images.githubusercontent.com/1566301/222281319-4303fd84-9817-4498-85e4-2ec2a95baaca.png)

In another window, edit the README and scroll to the very bottom of the page. Change the URLs so the one mentioned for `image` above is in the badge as follows:

```
[our CodeClimate project page]: https://codeclimate.com/github/PLACEHOLDER-TRIPAL/Template
[MaintainabilityBadge]: https://api.codeclimate.com/v1/badges/5d139ad7af5a3e2564ab/maintainability
[TestCoverageBadge]: https://api.codeclimate.com/v1/badges/5d139ad7af5a3e2564ab/test_coverage
```

Next you will need to create a github secret for the code climate reporter id. Specifically, you will want to look up the code climate test reporter ID by going to the the code climate page for your repository, clicking on "Repo Settings" and then "Test Coverage" in the left sidebar. Then scroll down to the bottom, there will be a "Test reporter ID" with a textfield containing a long alpha-numerical code. 

![codeclimate-test-reporter-id](https://user-images.githubusercontent.com/1566301/223853565-23c95db0-b133-4028-969e-989485b3a8b4.png)

This long code should be the value of the github secret, CODECLIMATE_TEST_REPORTER_ID, and will be used in the Test Coverage workflow to update our test coverage stats on Code Climate. To create the github secret you will want to follow [these instructions by GitHub](https://docs.github.com/en/actions/security-guides/encrypted-secrets#creating-encrypted-secrets-for-a-repository). The name of the secret should be "CODECLIMATE_TEST_REPORTER_ID" and the value should be the long code you looked up above.

![github-new-secret](https://user-images.githubusercontent.com/1566301/223853576-81301f13-ec22-4533-b20f-694102a8789d.png)
