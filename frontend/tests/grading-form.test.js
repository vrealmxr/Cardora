import test from 'node:test'
import assert from 'node:assert/strict'
import { gradingOptionsForEdit, gradingChoiceLabel } from '../src/utils/gradingPresentation.js'
import { getListingFormData } from '../dist-analytics-tests/analytics-harness.js'

test('new comics cannot select packaging as a grader; editing retains legacy values without changing the new-listing choices', () => {
  for (const locale of ['el', 'en']) {
    const data = getListingFormData(locale)
    const templates = data.listingCategoryTemplates
    const comics = templates.comics.attributeFields.find(field => field.name === 'gradedCompany')
    assert.ok(!comics.options.includes('Sealed'))
    assert.ok(templates.comics.conditions.includes('Sealed'))
    const original = [...comics.options]
    const editing = gradingOptionsForEdit(comics.options, 'Sealed')
    assert.ok(editing.includes('Sealed'))
    assert.deepEqual(comics.options, original)
    assert.ok(gradingChoiceLabel('Sealed', locale).includes(locale === 'en' ? 'not a grading company' : 'δεν είναι εταιρεία'))
    assert.ok(gradingOptionsForEdit(comics.options, 'Legacy grader').includes('Legacy grader'))
    for (const category of ['cards', 'comics']) {
      const fields = templates[category].attributeFields
      const grade = fields.find(field => field.name === 'grade')
      assert.ok(grade.help)
      assert.ok(!grade.placeholder.includes('Near Mint'))
      assert.ok(fields.some(field => field.name === 'authenticity'))
    }
    assert.ok(templates.figures.attributeFields.some(field => field.name === 'boxCondition'))
    assert.ok(templates.figures.attributeFields.some(field => field.name === 'displayStatus'))
  }
})
