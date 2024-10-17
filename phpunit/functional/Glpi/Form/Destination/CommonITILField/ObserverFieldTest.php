<?php

/**
 * ---------------------------------------------------------------------
 *
 * GLPI - Gestionnaire Libre de Parc Informatique
 *
 * http://glpi-project.org
 *
 * @copyright 2015-2024 Teclib' and contributors.
 * @copyright 2003-2014 by the INDEPNET Development Team.
 * @licence   https://www.gnu.org/licenses/gpl-3.0.html
 *
 * ---------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of GLPI.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 * ---------------------------------------------------------------------
 */

namespace tests\units\Glpi\Form\Destination\CommonITILField;

use CommonITILActor;
use DbTestCase;
use Glpi\Form\AnswersHandler\AnswersHandler;
use Glpi\Form\Destination\CommonITILField\ITILActorFieldConfig;
use Glpi\Form\Destination\CommonITILField\ITILActorFieldStrategy;
use Glpi\Form\Destination\CommonITILField\ObserverField;
use Glpi\Form\Destination\FormDestinationTicket;
use Glpi\Form\Form;
use Glpi\Form\QuestionType\QuestionTypeObserver;
use Glpi\Tests\FormBuilder;
use Glpi\Tests\FormTesterTrait;
use Group;
use Ticket;
use TicketTemplate;
use TicketTemplatePredefinedField;
use User;

final class ObserverFieldTest extends DbTestCase
{
    use FormTesterTrait;

    public function testObserverFromTemplate(): void
    {
        $form = $this->createAndGetFormWithMultipleActorsQuestions();
        $from_template_config = new ITILActorFieldConfig(
            ITILActorFieldStrategy::FROM_TEMPLATE
        );

        // The default GLPI's template doesn't have a predefined location
        $this->sendFormAndAssertTicketActors(
            form: $form,
            config: $from_template_config,
            answers: [],
            expected_actors_ids: []
        );

        $user = $this->createItem(User::class, ['name' => 'testObserverFromTemplate User']);
        $group = $this->createItem(Group::class, ['name' => 'testObserverFromTemplate Group']);

        // Set the user as default observer using predefined fields
        $this->createItem(TicketTemplatePredefinedField::class, [
            'tickettemplates_id' => getItemByTypeName(TicketTemplate::class, "Default", true),
            'num' => 66, // User observer
            'value' => $user->getID(),
        ]);
        $this->sendFormAndAssertTicketActors(
            form: $form,
            config: $from_template_config,
            answers: [],
            expected_actors_ids: [$user->getID()]
        );

        // Set the group as default observer using predefined fields
        $this->createItem(TicketTemplatePredefinedField::class, [
            'tickettemplates_id' => getItemByTypeName(TicketTemplate::class, "Default", true),
            'num' => 65, // Group observer
            'value' => $group->getID(),
        ]);
        $this->sendFormAndAssertTicketActors(
            form: $form,
            config: $from_template_config,
            answers: [],
            expected_actors_ids: [$user->getID(), $group->getID()]
        );
    }

    public function testObserverFormFiller(): void
    {
        $form = $this->createAndGetFormWithMultipleActorsQuestions();
        $form_filler_config = new ITILActorFieldConfig(
            ITILActorFieldStrategy::FORM_FILLER
        );

        // The default GLPI's template doesn't have a predefined location
        $this->sendFormAndAssertTicketActors(
            form: $form,
            config: $form_filler_config,
            answers: [],
            expected_actors_ids: []
        );

        $auth = $this->login();
        $this->sendFormAndAssertTicketActors(
            form: $form,
            config: $form_filler_config,
            answers: [],
            expected_actors_ids: [$auth->getUser()->getID()]
        );
    }

    public function testSpecificActors(): void
    {
        $form = $this->createAndGetFormWithMultipleActorsQuestions();

        $user = $this->createItem(User::class, [
            'name' => 'testSpecificActors User',
        ]);

        $group = $this->createItem(Group::class, ['name' => 'testSpecificActors Group']);

        // Specific value: User
        $this->sendFormAndAssertTicketActors(
            form: $form,
            config: new ITILActorFieldConfig(
                strategy: ITILActorFieldStrategy::SPECIFIC_VALUES,
                specific_itilactors_ids: [
                    User::getForeignKeyField() . '-' . $user->getID()
                ]
            ),
            answers: [],
            expected_actors_ids: [$user->getID()]
        );

        // Specific value: User and Group
        $this->sendFormAndAssertTicketActors(
            form: $form,
            config: new ITILActorFieldConfig(
                strategy: ITILActorFieldStrategy::SPECIFIC_VALUES,
                specific_itilactors_ids: [
                    User::getForeignKeyField() . '-' . $user->getID(),
                    Group::getForeignKeyField() . '-' . $group->getID()
                ]
            ),
            answers: [],
            expected_actors_ids: [$user->getID(), $group->getID()]
        );
    }

    public function testActorsFromSpecificQuestions(): void
    {
        $form = $this->createAndGetFormWithMultipleActorsQuestions();

        $user1 = $this->createItem(User::class, ['name' => 'testLocationFromSpecificQuestions User']);
        $user2 = $this->createItem(User::class, ['name' => 'testLocationFromSpecificQuestions User 2']);
        $group = $this->createItem(Group::class, ['name' => 'testLocationFromSpecificQuestions Group']);

        // Using answer from first question
        $this->sendFormAndAssertTicketActors(
            form: $form,
            config: new ITILActorFieldConfig(
                strategy: ITILActorFieldStrategy::SPECIFIC_ANSWERS,
                specific_question_ids: [$this->getQuestionId($form, "Observer 1")]
            ),
            answers: [
                "Observer 1" => [
                    User::getForeignKeyField() . '-' . $user1->getID(),
                ],
                "Observer 2" => [
                    User::getForeignKeyField() . '-' . $user2->getID(),
                    Group::getForeignKeyField() . '-' . $group->getID(),
                ],
            ],
            expected_actors_ids: [$user1->getID()]
        );

        // Using answer from first and second question
        $this->sendFormAndAssertTicketActors(
            form: $form,
            config: new ITILActorFieldConfig(
                strategy: ITILActorFieldStrategy::SPECIFIC_ANSWERS,
                specific_question_ids: [
                    $this->getQuestionId($form, "Observer 1"),
                    $this->getQuestionId($form, "Observer 2")
                ]
            ),
            answers: [
                "Observer 1" => [
                    User::getForeignKeyField() . '-' . $user1->getID(),
                ],
                "Observer 2" => [
                    User::getForeignKeyField() . '-' . $user2->getID(),
                    Group::getForeignKeyField() . '-' . $group->getID(),
                ],
            ],
            expected_actors_ids: [$user1->getID(), $user2->getID(), $group->getID()]
        );
    }

    public function testActorsFromLastValidQuestion(): void
    {
        $form = $this->createAndGetFormWithMultipleActorsQuestions();
        $last_valid_answer_config = new ITILActorFieldConfig(
            ITILActorFieldStrategy::LAST_VALID_ANSWER
        );

        $user1 = $this->createItem(User::class, ['name' => 'testLocationFromSpecificQuestions User']);
        $user2 = $this->createItem(User::class, ['name' => 'testLocationFromSpecificQuestions User 2']);
        $group = $this->createItem(Group::class, ['name' => 'testLocationFromSpecificQuestions Group']);

        // With multiple answers submitted
        $this->sendFormAndAssertTicketActors(
            form: $form,
            config: $last_valid_answer_config,
            answers: [
                "Observer 1" => [
                    User::getForeignKeyField() . '-' . $user1->getID(),
                ],
                "Observer 2" => [
                    User::getForeignKeyField() . '-' . $user2->getID(),
                    Group::getForeignKeyField() . '-' . $group->getID(),
                ],
            ],
            expected_actors_ids: [$user2->getID(), $group->getID()]
        );

        // Only first answer was submitted
        $this->sendFormAndAssertTicketActors(
            form: $form,
            config: $last_valid_answer_config,
            answers: [
                "Observer 1" => [
                    User::getForeignKeyField() . '-' . $user1->getID(),
                ],
            ],
            expected_actors_ids: [$user1->getID()]
        );

        // Only second answer was submitted
        $this->sendFormAndAssertTicketActors(
            form: $form,
            config: $last_valid_answer_config,
            answers: [
                "Observer 2" => [
                    User::getForeignKeyField() . '-' . $user2->getID(),
                    Group::getForeignKeyField() . '-' . $group->getID(),
                ],
            ],
            expected_actors_ids: [$user2->getID(), $group->getID()]
        );

        // No answers, fallback to default value
        $this->sendFormAndAssertTicketActors(
            form: $form,
            config: $last_valid_answer_config,
            answers: [],
            expected_actors_ids: []
        );

        // Try again with a different template value
        $this->createItem(TicketTemplatePredefinedField::class, [
            'tickettemplates_id' => getItemByTypeName(TicketTemplate::class, "Default", true),
            'num' => 66, // User observer
            'value' => $user1->getID(),
        ]);
        $this->sendFormAndAssertTicketActors(
            form: $form,
            config: $last_valid_answer_config,
            answers: [],
            expected_actors_ids: [$user1->getID()]
        );
    }

    private function sendFormAndAssertTicketActors(
        Form $form,
        ITILActorFieldConfig $config,
        array $answers,
        array $expected_actors_ids,
    ): void {
        // Insert config
        $destinations = $form->getDestinations();
        $this->assertCount(1, $destinations);
        $destination = current($destinations);
        $this->updateItem(
            $destination::getType(),
            $destination->getId(),
            ['config' => [(new ObserverField())->getKey() => $config->jsonSerialize()]],
            ["config"],
        );

        // The provider use a simplified answer format to be more readable.
        // Rewrite answers into expected format.
        $formatted_answers = [];
        foreach ($answers as $question => $answer) {
            $key = $this->getQuestionId($form, $question);
            $formatted_answers[$key] = $answer;
        }

        // Submit form
        $answers_handler = AnswersHandler::getInstance();
        $answers = $answers_handler->saveAnswers(
            $form,
            $formatted_answers,
            getItemByTypeName(\User::class, TU_USER, true)
        );

        // Get created ticket
        $created_items = $answers->getCreatedItems();
        $this->assertCount(1, $created_items);
        /** @var Ticket $ticket */
        $ticket = current($created_items);

        // Check actors
        $this->assertEquals(
            array_map(fn(array $actor) => $actor['items_id'], $ticket->getActorsForType(CommonITILActor::OBSERVER)),
            $expected_actors_ids
        );
    }

    private function createAndGetFormWithMultipleActorsQuestions(): Form
    {
        $builder = new FormBuilder();
        $builder->addQuestion("Observer 1", QuestionTypeObserver::class, '');
        $builder->addQuestion(
            "Observer 2",
            QuestionTypeObserver::class,
            '',
            json_encode(['is_multiple_actors' => '1'])
        );
        $builder->addDestination(
            FormDestinationTicket::class,
            "My ticket",
        );
        return $this->createForm($builder);
    }
}