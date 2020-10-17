<?php


namespace App\Controller;


use Sonata\AdminBundle\Controller\CRUDController;
use Symfony\Component\HttpFoundation\RedirectResponse;

class BookMarkAdminController extends CRUDController
{
    protected function redirectTo($object)
    {

        $request = $this->getRequest();

        $url = false;

        if (null !== $request->get('btn_update_and_list')) {
            return $this->redirectToList();
        }
        if (null !== $request->get('btn_create_and_list')) {
            return $this->redirectToList();
        }

        if (null !== $request->get('btn_create_and_create')) {
            $params = [];
            if ($this->admin->hasActiveSubClass()) {
                $params['subclass'] = $request->get('subclass');
            }
            $url = $this->admin->generateUrl('create', $params);
        }

        if ('DELETE' === $this->getRestMethod()) {
            return $this->redirectToList();
        }
        if (null !== $request->get('btn_create_and_edit')) {
            $url = $this->admin->generateUrl('show', ["id" => $object->getId()]);
        }
        if (!$url) {
            return $this->redirectToList();
        }

        return new RedirectResponse($url);
    }

    public function editAction($deprecatedId = null)
    {
        return $this->redirectToList();
    }
}