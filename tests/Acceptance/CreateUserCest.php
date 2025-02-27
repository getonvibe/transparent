<?php


namespace Tests\Acceptance;

use Tests\Support\AcceptanceTester;
use Codeception\Attribute\Depends;
use Tests\Support\Page\Acceptance\Login;

class CreateUserCest
{
    public function _before(AcceptanceTester $I, Login $loginPage)
    {
        $loginPage->login('test@leantime.io', 'test');
    }

    #[Depends('Tests\Acceptance\LoginCest:loginSuccessfully')]
    public function createAUser(AcceptanceTester $I)
    {
        $I->wantTo('Create a user');
        $I->amOnPage('/users/showAll');
        $I->click('Add User');
        $I->waitForElement('#firstname', 10);
        $I->fillField('#firstname', 'John');
        $I->fillField('#lastname', 'Doe');
        $I->selectOption('#role', 'Read Only');
        $I->selectOption('#client', 'Not assigned to a client');
        $I->fillField('#user', 'john@doe.com');
        $I->fillField('#phone', '1234567890');
        $I->fillField('#jobTitle', 'Testing');
        $I->fillField('#jobLevel', 'Testing');
        $I->fillField('#department', 'Testing');
        $I->click('Invite User');
        $I->waitForElement('.growl', 10);
        $I->see('New user invited successfully');
    }

    #[Depends('Tests\Acceptance\LoginCest:loginSuccessfully')]
    public function editAUser(AcceptanceTester $I)
    {
        $I->wantTo('Edit a user');
        $I->amOnPage('/users/editUser/1/');
        $I->see('Edit User');
        $I->fillField(['name' => 'jobTitle'], 'Testing');
        $I->click('Save');
        $I->see('User edited successfully');
    }
}
