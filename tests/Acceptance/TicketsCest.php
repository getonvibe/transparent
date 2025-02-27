<?php

namespace Tests\Acceptance;

use Tests\Support\AcceptanceTester;
use Tests\Support\Page\Acceptance\Login;
use Codeception\Attribute\Depends;

class TicketsCest
{
    public function _before(AcceptanceTester $I, Login $loginPage)
    {
        $loginPage->login('test@leantime.io', 'test');
    }

    public function createTicket(AcceptanceTester $I)
    {
        $I->wantTo('Create a ticket');

        $I->amOnPage('/tickets/showKanban#/tickets/newTicket');
        $I->waitForElementVisible('.main-title-input', 30);
        $I->fillField('.main-title-input', 'Test Ticket');
        $I->click('#tags_tagsinput');
        $I->type('test-tag,');
        $I->click('.mce-content-body');
        $I->click('#ticketDescription_ifr');
        $I->type('Test Description');
        $I->waitForElementClickable('//*[@id="ticketdetails"]//input[@name="saveTicket"][@type="submit"]', 30);
        $I->click('//*[@id="ticketdetails"]//input[@name="saveTicket"][@type="submit"]');
        $I->seeInDatabase('zp_tickets', [
            'id' => 10,
            'headline' => 'Test Ticket'
        ]);
    }

    #[Depends('createTicket')]
    public function editTicket(AcceptanceTester $I)
    {
        $I->wantTo('Edit a ticket');

        $I->amOnPage('/tickets/showKanban#/tickets/showTicket/10');
        $I->waitForElementClickable('.nyroModalCont .mce-content-body', 30);
        $I->click('.mce-content-body');
        $I->click('#ticketDescription_ifr');
        $I->type('Test Description Edited');
        $I->waitForElementClickable('//*[@id="ticketdetails"]//input[@name="saveTicket"][@type="submit"]', 30);
        $I->click('//*[@id="ticketdetails"]//input[@name="saveTicket"][@type="submit"]');
        $I->waitForElement('.growl', 10);
        $I->see('To-Do was saved successfully');
    }
}
