@mod @mod_codelab
Feature: Gestión básica de una actividad CodeLab
  Como docente
  quiero crear y configurar una actividad CodeLab en mi curso
  para que los estudiantes escriban y entreguen código evaluado automáticamente.

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Curso 1  | C1        | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email             |
      | docente1 | Ana       | Docente  | ana@example.com   |
      | alumno1  | Beto      | Alumno   | beto@example.com  |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | docente1 | C1     | editingteacher |
      | alumno1  | C1     | student        |

  @javascript
  Scenario: El docente crea una actividad CodeLab
    Given I am on the "Curso 1" course page logged in as docente1
    And I turn editing mode on
    When I add a "CodeLab" activity to course "Curso 1" section "1" and I fill the form with:
      | Name | Suma de dos números |
    Then I should see "Suma de dos números"

  @javascript
  Scenario: El estudiante ve la actividad publicada
    Given the following "activities" exist:
      | activity | name                | intro              | course | idnumber |
      | codelab  | Suma de dos números | Escribe una función | C1     | codelab1 |
    When I am on the "Suma de dos números" "codelab activity" page logged in as alumno1
    Then I should see "Suma de dos números"
