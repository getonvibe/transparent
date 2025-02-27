<?php

namespace leantime\domain\controllers {

    use leantime\core;
    use leantime\core\controller;
    use leantime\domain\models\auth\roles;
    use leantime\domain\repositories;
    use leantime\domain\services;
    use leantime\domain\models;
    use leantime\domain\services\auth;

    class newTicket extends controller
    {
        private services\projects $projectService;
        private services\tickets $ticketService;
        private services\sprints $sprintService;
        private services\files $fileService;
        private services\comments $commentService;
        private services\timesheets $timesheetService;
        private services\users $userService;

        public function init(
            services\projects $projectService,
            services\tickets $ticketService,
            services\sprints $sprintService,
            services\files $fileService,
            services\comments $commentService,
            services\timesheets $timesheetService,
            services\users $userService
        ) {
            auth::authOrRedirect([roles::$owner, roles::$admin, roles::$manager, roles::$editor]);

            $this->projectService = $projectService;
            $this->ticketService = $ticketService;
            $this->sprintService = $sprintService;
            $this->fileService = $fileService;
            $this->commentService = $commentService;
            $this->timesheetService = $timesheetService;
            $this->userService = $userService;

            if (!isset($_SESSION['lastPage'])) {
                $_SESSION['lastPage'] = BASE_URL . "/tickets/showKanban/";
            }
        }


        public function get()
        {
            $ticket = app()->make(models\tickets::class, [
                [
                    "userLastname" => $_SESSION['userdata']["name"],
                    "status" => 3,
                    "projectId" => $_SESSION['currentProject'],
                    "sprint" => $_SESSION['currentSprint'] ?? ''
                ]
            ]);

            $ticket->date =  $this->language->getFormattedDateString(date("Y-m-d H:i:s"));

            $this->tpl->assign('ticket', $ticket);
            $this->tpl->assign('ticketParents', $this->ticketService->getAllPossibleParents($ticket));
            $this->tpl->assign('statusLabels', $this->ticketService->getStatusLabels());
            $this->tpl->assign('ticketTypes', $this->ticketService->getTicketTypes());
            $this->tpl->assign('efforts', $this->ticketService->getEffortLabels());
            $this->tpl->assign('priorities', $this->ticketService->getPriorityLabels());
            $this->tpl->assign('milestones', $this->ticketService->getAllMilestones($_SESSION["currentProject"]));
            $this->tpl->assign('sprints', $this->sprintService->getAllSprints($_SESSION["currentProject"]));

            $this->tpl->assign('kind', $this->timesheetService->getLoggableHourTypes());
            $this->tpl->assign('ticketHours', 0);
            $this->tpl->assign('userHours', 0);

            $this->tpl->assign('timesheetsAllHours', 0);
            $this->tpl->assign('remainingHours', 0);

            $this->tpl->assign('userInfo', $this->userService->getUser($_SESSION['userdata']['id']));
            $this->tpl->assign('users', $this->projectService->getUsersAssignedToProject($_SESSION["currentProject"]));

            $this->tpl->displayPartial('tickets.newTicketModal');
        }

        public function post($params)
        {

            if (isset($params['saveTicket']) || isset($params['saveAndCloseTicket'])) {
                $result = $this->ticketService->addTicket($params);

                if (is_array($result) === false) {
                    $this->tpl->setNotification($this->language->__("notifications.ticket_saved"), "success");

                    if (isset($params["saveAndCloseTicket"]) === true && $params["saveAndCloseTicket"] == 1) {
                        $this->tpl->redirect(BASE_URL . "/tickets/showTicket/" . $result . "?closeModal=1");
                    } else {
                        $this->tpl->redirect(BASE_URL . "/tickets/showTicket/" . $result);
                    }
                } else {
                    $this->tpl->setNotification($this->language->__($result["msg"]), "error");

                    $ticket = app()->make(models\tickets::class, [$params]);
                    $ticket->userLastname = $_SESSION['userdata']["name"];

                    $this->tpl->assign('ticket', $ticket);
                    $this->tpl->assign('statusLabels', $this->ticketService->getStatusLabels());
                    $this->tpl->assign('ticketTypes', $this->ticketService->getTicketTypes());
                    $this->tpl->assign('efforts', $this->ticketService->getEffortLabels());
                    $this->tpl->assign('priorities', $this->ticketService->getPriorityLabels());
                    $this->tpl->assign('milestones', $this->ticketService->getAllMilestones($_SESSION["currentProject"]));
                    $this->tpl->assign('sprints', $this->sprintService->getAllSprints($_SESSION["currentProject"]));

                    $this->tpl->assign('kind', $this->timesheetService->getLoggableHourTypes());
                    $this->tpl->assign('ticketHours', 0);
                    $this->tpl->assign('userHours', 0);

                    $this->tpl->assign('timesheetsAllHours', 0);
                    $this->tpl->assign('remainingHours', 0);

                    $this->tpl->assign('userInfo', $this->userService->getUser($_SESSION['userdata']['id']));
                    $this->tpl->assign('users', $this->projectService->getUsersAssignedToProject($_SESSION["currentProject"]));

                    $this->tpl->displayPartial('tickets.newTicketModal');
                }
            }
        }
    }

}
