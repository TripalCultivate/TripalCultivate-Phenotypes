<?php

namespace Drupal\Tests\trpcultivate_phenotypes\Functional\Display;

use Drupal\Core\Url;
use Drupal\Component\Utility\Html;
use Drupal\Tests\tripal_chado\Functional\ChadoTestBrowserBase;
use Drupal\KernelTests\AssertContentTrait;

/**
 * Tests Tripal Cultivate Phenotypes Validation Result Window.
 *
 * @group trpcultivate_phenotypes
 * @group displays
 */
class DisplayValidationResultWindowTest extends ChadoTestBrowserBase {

  use AssertContentTrait;

  /**
   * Theme used in the test environment.
   *
   * @var string
   */
  protected $defaultTheme = 'stark';

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'tripal',
    'tripal_chado',
    'tripal_layout',
    'trpcultivate',
    'trpcultivate_phenotypes',
  ];

  /**
   * Drupal render service.
   *
   * @var Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * {@inheritDoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Set test environment.
    \Drupal::state()->set('is_a_test_environment', TRUE);

    $this->renderer = $this->container->get('renderer');
  }

  /**
   * Data Provider: Provides the template with a set of validation item values.
   *
   * @return array
   *   Each scenario/element is an array with the following values.
   *   - A string, human-readable short description of the test scenario.
   *   - One or more validation status arrays. Each array contains the following
   *     keys:
   *     - 'title': Validation title text that is paired with appropriate status
   *        icon in the markup output.
   *     - 'status': The status of the validation that indicates if it passed
   *        (pass), failed (fail) or upcoming task (todo).
   *     - 'details': A Drupal render array that will generate the required
   *        markup to output any failed items, only if 'status'
   *        is set to 'fail'.
   *   - The expected class name an item is tagged with. Each validation item
   *     corresponds to one class name based on the value of the status key.
   */
  public function provideValidationResultRenderArray() {

    return [
      // #0: A passing validation.
      [
        'passing validator',
        [
          [
            'title' => 'Validation Title Text - Pass',
            'status' => 'pass',
            'details' => [],
          ],
        ],
        ['tcp-validate-pass'],
      ],
      // #1: An upcoming validation.
      [
        'upcoming validator',
        [
          [
            'title' => 'Validation Title Text - Todo',
            'status' => 'todo',
            'details' => [],
          ],
        ],
        ['tcp-validate-todo'],
      ],
      // #2: A failed validator: one item without bullet point.
      [
        'failed and one item without bullet point',
        [
          [
            'title' => 'Validation Title Text - Fail',
            'status' => 'fail',
            'details' => [
              '#type' => 'item',
              '#title' => 'Validator Case Message',
              '#markup' => 'Failed Item Name: Failed Item Value',
            ],
          ],
        ],
        ['tcp-validate-fail'],
      ],
      // #3: A failed validator: one item with bullet point.
      [
        'failed and one item with bullet point',
        [
          [
            'title' => 'Validation Title Text - Fail',
            'status' => 'fail',
            'details' => [
              '#type' => 'item',
              '#title' => 'Validator Case Message',
              'items' => [
                '#theme' => 'item_list',
                '#type' => 'ul',
                '#items' => [
                  'Failed Item #1',
                ],
              ],
            ],
          ],
        ],
        ['tcp-validate-fail'],
      ],
      // #4: A failed validator: many items with bullet points.
      [
        'failed and many items with bullet points',
        [
          [
            'title' => 'Validation Title Text - Fail',
            'status' => 'fail',
            'details' => [
              '#type' => 'item',
              '#title' => 'Validator Case Message',
              'items' => [
                '#theme' => 'item_list',
                '#type' => 'ul',
                '#items' => [
                  'Failed Item #1',
                  'Failed Item #2',
                  'Failed Item #3',
                  'Failed Item #4',
                  'Failed Item #5',
                  'Failed Item #6',
                  'Failed Item #7',
                  'Failed Item #8',
                  'Failed Item #9',
                  'Failed Item #10',
                  'Failed Item #11',
                  'Failed Item #12',
                  'Failed Item #13',
                  'Failed Item #14',
                  'Failed Item #15',
                ],
              ],
            ],
          ],
        ],
        ['tcp-validate-fail'],
      ],
      // #5: A failed validator: one row in a table element.
      [
        'failed and one row in a table element',
        [
          [
            'title' => 'Validation Title Text - Fail',
            'status' => 'fail',
            'details' => [
              '#type' => 'html_tag',
              '#tag' => 'ul',
              'lists' => [
                [
                  '#type' => 'html_tag',
                  '#tag' => 'li',
                  'table' => [
                    '#type' => 'table',
                    '#caption' => 'Validator Case Message',
                    '#header' => ['Header 1', 'Header 2'],
                    '#attributes' => [],
                    '#rows' => [
                      ['Row 1 - Value 1', 'Row 1 - Value 2'],
                    ],
                  ],
                ],
              ],
            ],
          ],
        ],
        ['tcp-validate-fail'],
      ],
      // #6: Failed validator: one row in table element with wrapping CSS rule.
      [
        'failed and one row in a table element with attributes',
        [
          [
            'title' => 'Validation Title Text - Fail',
            'status' => 'fail',
            'details' => [
              '#type' => 'html_tag',
              '#tag' => 'ul',
              'lists' => [
                [
                  '#type' => 'html_tag',
                  '#tag' => 'li',
                  'table' => [
                    '#type' => 'table',
                    '#caption' => 'Validator Case Message',
                    '#header' => ['Header 1', 'Header 2'],
                    '#attributes' => ['class' => ['tcp-raw-row']],
                    '#rows' => [
                      ['Row #1 - Value #1', 'Row #1 - Value #2'],
                    ],
                  ],
                ],
              ],
            ],
          ],
        ],
        ['tcp-validate-fail'],
      ],
      // #7: A failed validator: many rows in a table element.
      [
        'failed and many rows in a table element',
        [
          [
            'title' => 'Validation Title Text - Fail',
            'status' => 'fail',
            'details' => [
              '#type' => 'html_tag',
              '#tag' => 'ul',
              'lists' => [
                [
                  '#type' => 'html_tag',
                  '#tag' => 'li',
                  'table' => [
                    '#type' => 'table',
                    '#caption' => 'Validator Case Message',
                    '#header' => ['Header 1', 'Header 2', 'Header 3'],
                    '#attributes' => [],
                    '#rows' => [
                      ['Row #1 - Value #1', 'Row #1 - Value #2', 'Row #1 - Value #3'],
                      ['Row #2 - Value #1', 'Row #2 - Value #2', 'Row #2 - Value #3'],
                      ['Row #3 - Value #1', 'Row #3 - Value #2', 'Row #3 - Value #3'],
                      ['Row #4 - Value #1', 'Row #4 - Value #2', 'Row #4 - Value #3'],
                      ['Row #5 - Value #1', 'Row #5 - Value #2', 'Row #5 - Value #3'],
                      ['Row #6 - Value #1', 'Row #6 - Value #2', 'Row #6 - Value #3'],
                      ['Row #7 - Value #1', 'Row #7 - Value #2', 'Row #7 - Value #3'],
                      ['Row #8 - Value #1', 'Row #8 - Value #2', 'Row #8 - Value #3'],
                      ['Row #9 - Value #1', 'Row #9 - Value #2', 'Row #9 - Value #3'],
                      ['Row #10 - Value #1', 'Row #10 - Value #2', 'Row #10 - Value #3'],
                      ['Row #11 - Value #1', 'Row #11 - Value #2', 'Row #11 - Value #3'],
                      ['Row #12 - Value #1', 'Row #12 - Value #2', 'Row #12 - Value #3'],
                      ['Row #13 - Value #1', 'Row #13 - Value #2', 'Row #13 - Value #3'],
                      ['Row #14 - Value #1', 'Row #14 - Value #2', 'Row #14 - Value #3'],
                      ['Row #15 - Value #1', 'Row #15 - Value #2', 'Row #15 - Value #3'],
                    ],
                  ],
                ],
              ],
            ],
          ],
        ],
        ['tcp-validate-fail'],
      ],
      // #8: An image.
      [
        'an image',
        [
          [
            'title' => 'Validation Title Text - Fail',
            'status' => 'fail',
            'details' => [
              '#theme' => 'image',
              '#style_name' => 'thumbnail',
              '#uri' => 'public://logo.svg',
            ],
          ],
        ],
        ['tcp-validate-fail'],
      ],
      // #9: A link type Drupal render array.
      [
        'a link',
        [
          [
            'title' => 'Validation Title Text - Fail',
            'status' => 'fail',
            'details' => [
              '#type' => 'link',
              '#title' => 'KnowPulse',
              '#url' => Url::fromUri('https://knowpulse.usask.ca'),
            ],
          ],
        ],
        ['tcp-validate-fail'],
      ],
      // #10: Pass, fail and todo concurrent validation items.
      [
        'concurrent validation items',
        [
          [
            'title' => 'Validation Title Text - Pass',
            'status' => 'pass',
            'details' => [],
          ],
          [
            'title' => 'Validation Title Text - Fail',
            'status' => 'fail',
            'details' => [
              '#type' => 'item',
              '#title' => 'Validator Case Message',
              'items' => [
                '#theme' => 'item_list',
                '#type' => 'ul',
                '#items' => [
                  'Failed Item #1',
                ],
              ],
            ],
          ],
          [
            'title' => 'Validation Title Text - Todo',
            'status' => 'todo',
            'details' => [],
          ],
        ],
        [
          'tcp-validate-pass',
          'tcp-validate-fail',
          'tcp-validate-todo',
        ],
      ],
    ];
  }

  /**
   * Test an object as a render array.
   */
  public function testObjectAsRenderArray() {

    $object_render_array = [
      [
        'title' => 'Validation Title - Fail',
        'status' => 'fail',
        'details' => new \stdClass(),
      ],
    ];

    $validation_window = [
      '#type' => 'inline_template',
      '#theme' => 'validation_result_window',
      '#data' => [
        'validation_result' => $object_render_array,
      ],
    ];

    $exception_caught = FALSE;
    $exception_message = '';
    $expected_message = 'Object of type stdClass cannot be printed.';
    try {
      $this->renderer->renderRoot($validation_window);
    }
    catch (\Exception $e) {
      $exception_caught = TRUE;
      $exception_message = $e->getMessage();
    }

    $this->assertTrue(
      $exception_caught,
      'An exception message was expected when passing an object as render array'
    );

    $this->assertStringContainsString(
      $expected_message,
      $exception_message,
      'The exception message does not match expected message when passing an object'
    );
  }

  /**
   * Test result window display.
   *
   * @param string $scenario
   *   A string, human-readable short description of the test scenario.
   * @param array $validation_result_input
   *   One or more validation status arrays. Each array contains the following
   *     keys:
   *     - 'title': Validation title text that is paired with appropriate status
   *        icon in the markup output.
   *     - 'status': The status of the validation that indicates if it passed
   *        (pass), failed (fail) or upcoming task (todo).
   *     - 'details': A Drupal render array that will generate the required
   *        markup to output any failed items, only if 'status'
   *        is set to 'fail'.
   * @param array $expected_class
   *   The expected class name an item is tagged with. Each validation item
   *   corresponds to one class name based on the value of the status key.
   *
   * @dataProvider provideValidationResultRenderArray
   */
  public function testResultWindowDisplay(string $scenario, array $validation_result_input, array $expected_class) {

    $validation_window = [
      '#type' => 'inline_template',
      '#theme' => 'validation_result_window',
      '#data' => [
        'validation_result' => $validation_result_input,
      ],
    ];

    // This is now the validation result window markup with all validation
    // result details rendered.
    $validation_window_markup = $this->renderer->renderRoot($validation_window);

    // For every validation item in a test scenario, test that:
    // 1. The number of validation items matches the number of list markup (li).
    // Break the validation window markup into each validation item to allow
    // matching expected output within each validation item. This is done
    // using the Kernel AssertContent trait in order to break the rendered HTML
    // into SimpleXML objects using CSS selectors.
    $this->setRawContent($validation_window_markup);
    $returned_validationitem_markup = [];
    foreach ($this->cssSelect('li.tcp-validation-item') as $selected_item) {
      $returned_validationitem_markup[] = [
        'markup' => $selected_item->asXML(),
        'element' => $selected_item,
      ];
    }

    $this->assertEquals(
      count($validation_result_input),
      count($returned_validationitem_markup),
      'The number of validation items do not match the number of list markup items in scenario ' . $scenario
    );

    // Now for each validation item:
    foreach ($validation_result_input as $i => $expected_validation_item) {
      $returned_markup = $returned_validationitem_markup[$i]['markup'];
      $returned_element = $returned_validationitem_markup[$i]['element'];

      // 2. The validation item has been set the correct status-based class.
      $class_name = $expected_class[$i];
      $this->assertStringContainsString(
        $class_name,
        $returned_markup,
        'The class name ' . $class_name . ' of validation item #' . $i . ' in scenario "' . $scenario . '" was not found in the expected rendered validation item.'
      );

      // 3. The title of validation item was rendered in the correct item.
      $title_text = $expected_validation_item['title'];
      $this->assertStringContainsString(
        $title_text,
        $returned_markup,
        'The title "' . $title_text . '" of validation item #' . $i . ' in scenario "' . $scenario . '" was not found in the expected rendered validation item.'
      );

      // 5. The details rendered markup is present in the correct item.
      // -- a) we do not expect any markup so confirm there isn't any.
      if (empty($expected_validation_item['details'])) {
        // We expect the rendered details to be within a div wrapper in the
        // validation item. Therefore, in this case we just want to check that
        // the div element in this validation item does not have any children.
        $this->assertEmpty(
          $returned_element->div->children(),
          'The details section of validation item #' . $i . ' in scenario "' . $scenario . '" was supposed to be empty but was not.'
        );
      }
      // -- b) we do expect markup so confirm it matches what we got.
      else {
        $details_markup = $this->renderer->renderRoot($expected_validation_item['details']);
        // Mimic the AssertContentTrait::parse() method on the details markup
        // in order to compare it with the parsed validation item. This approach
        // is used to remove false failures caused by whitespace and slight
        // differences in HTML syntax.
        $details_dom = Html::load($details_markup);
        $expected_details_element = @simplexml_import_dom($details_dom);
        $expected_details_element = $expected_details_element->body;
        $this->assertIsObject(
          $expected_details_element,
          'The rendered and parsed details element of validation item #' . $i . ' in scenario "' . $scenario . '" is not valid.',
        );
        // Unfortunatly we cannot simply check that the returned element
        // contains the Details element. Instead, we use our knowledge
        // of the validation item setup to return the children of the details
        // div section wrapper and ensure they match the details element.
        $rendered_details_element = $returned_element->div;
        unset($rendered_details_element->attributes()->class);
        // @debug print "Expected Details Element: " . print_r($expected_details_element, TRUE);
        // @debug print "Rendered Details Element: " . print_r($rendered_details_element, TRUE);
        $this->assertEquals(
          $expected_details_element,
          $rendered_details_element,
          'The details markup of validation item #' . $i . ' in scenario "' . $scenario . '" was not found in the expected rendered validation item.'
        );
      }
    }
  }

}
