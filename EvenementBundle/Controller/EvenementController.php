<?php

namespace EvenementBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use EvenementBundle\Form\EvenementType;
use EvenementBundle\Entity\Evenement;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

class EvenementController extends Controller
{
    /**
     * Ajouter
     */
    public function ajouterAdminAction(Request $request)
    {
        $evenement = new Evenement;
        $form = $this->get('form.factory')->create(EvenementType::class, $evenement);

        /* Récéption du formulaire */
        if ($form->handleRequest($request)->isValid()){
            $evenement->getReferencement()->UploadOgimage();
            $evenement->uploadImage();

            $em = $this->getDoctrine()->getManager();
            $em->persist($evenement);
            $em->flush();

            $request->getSession()->getFlashBag()->add('succes', 'Evénement enregistré avec succès');
            return $this->redirect($this->generateUrl('admin_evenement_manager'));
        }

        return $this->render('EvenementBundle:Admin:ajouter.html.twig',
            array(
                'form' => $form->createView()
            )
        );
    }

    /**
     * Gestion
     */
    public function managerAdminAction(Request $request)
    {
        /* Services */
        $rechercheService = $this->get('recherche.service');
        $recherches = $rechercheService->setRecherche('evenement_manager', array(
                'recherche',
                'langue'
            )
        );

        /* La liste des événements */
        $evenements = $this->getDoctrine()
                           ->getRepository('EvenementBundle:Evenement')
                           ->getAllEvenements($recherches['recherche'], $recherches['langue'], null, true);

        $paginator  = $this->get('knp_paginator');
        $pagination = $paginator->paginate(
            $evenements, /* query NOT result */
            $request->query->getInt('page', 1)/*page number*/,
            50/*limit per page*/
        );

        /* La liste des langues */
        $langues = $this->getDoctrine()->getRepository('GlobalBundle:Langue')->findAll();

        return $this->render('EvenementBundle:Admin:manager.html.twig',array(
                'pagination' => $pagination,
                'recherches' => $recherches,
                'langues' => $langues
            )
        );
    }

    /**
     * Publication
     */
    public function publierAdminAction(Request $request, Evenement $evenement){

        if($request->isXmlHttpRequest()){
            $state = $evenement->reverseState();
            $evenement->setIsActive($state);

            $em = $this->getDoctrine()->getManager();
            $em->persist($evenement);
            $em->flush();

            return new JsonResponse(array('state' => $state));
        }

    }

    /**
     * Supprimer
     */
    public function supprimerAdminAction(Request $request, Evenement $evenement)
    {
        $em = $this->getDoctrine()->getManager();
        $em->remove($evenement);
        $em->flush();

        $request->getSession()->getFlashBag()->add('succes', 'Evenement supprimé avec succès');
        return $this->redirect($this->generateUrl('admin_evenement_manager'));
    }

    /**
     * Modifier
     */
    public function modifierAdminAction(Request $request, Evenement $evenement)
    {
        $form = $this->get('form.factory')->create(EvenementType::class, $evenement);

        /* Récéption du formulaire */
        if ($form->handleRequest($request)->isValid()){
            $evenement->getReferencement()->UploadOgimage();
            $evenement->uploadImage();

            $em = $this->getDoctrine()->getManager();
            $em->persist($evenement);
            $em->flush();

            $request->getSession()->getFlashBag()->add('succes', 'Evenement enregistré avec succès');
            return $this->redirect($this->generateUrl('admin_evenement_manager'));
        }

        /* BreadCrumb */
        $breadcrumb = array(
            'Accueil' => $this->generateUrl('admin_page_index'),
            'Gestion des événements' => $this->generateUrl('admin_evenement_manager'),
            'Modifier un événement' => ''
        );

        return $this->render('EvenementBundle:Admin:modifier.html.twig',
            array(
                'breadcrumb' => $breadcrumb,
                'evenement' => $evenement,
                'form' => $form->createView()
            )
        );

    }

    /**
     * Supprimer l'image
     */
    public function AdminSupprimerImageAction(Request $request, Evenement $evenement)
    {
        if($request->isXmlHttpRequest()){
            $em = $this->getDoctrine()->getManager();
            $evenement->setImage(null);
            $em->flush();

            return new JsonResponse(array('state' => 'ok'));
        }
    }

    /**
     * Manager client
     */
    public function managerClientAction(Request $request)
    {

        /* Services */
        $rechercheService = $this->get('recherche.service');
        $recherches = $rechercheService->setRecherche('evenements', array(
                'categorie',
            )
        );

        /* La liste des événements */
        $evenements = $this->getDoctrine()
                           ->getRepository('EvenementBundle:Evenement')
                           ->getAllEvenements(null, $request->getLocale(), $recherches['categorie'], false);

        /* L'événement mis en avant */
        $avant = $this->getDoctrine()
                      ->getRepository('EvenementBundle:Evenement')
                      ->getAvantEvenement($request->getLocale());

        /* La liste des catégories */
        $categories = $this->getDoctrine()
                           ->getRepository('EvenementBundle:Categorie')
                           ->getAlLCategorie($request->getLocale());

        $paginator  = $this->get('knp_paginator');
        $pagination = $paginator->paginate(
            $evenements, /* query NOT result */
            $request->query->getInt('page', 1) /*page number*/,
            9 /*limit per page*/
        );

        return $this->render('EvenementBundle:Client:manager.html.twig', array(
                'pagination' => $pagination,
                'categories' => $categories,
                'recherches' => $recherches,
                'avant' => $avant
            )
        );
    }

    /*
     * View
     */
    public function viewClientAction($id)
    {
        /* Evenement en cours */
        $evenement = $this->getDoctrine()
                          ->getRepository('EvenementBundle:Evenement')
                          ->getCurrentEvenement($id);

        if(is_null($evenement)) throw new NotFoundHttpException('Cette page n\'est pas disponible');

        /* BreadCrumb */
        $breadcrumb = array(
            $this->get('translator')->trans('evenement.client.view.breadcrumb.niveau1') => $this->generateUrl('client_evenement_manager'),
            $evenement->getTitre() => ''
        );

        return $this->render( 'EvenementBundle:Client:view.html.twig',array(
                'evenement' => $evenement,
                'breadcrumb' => $breadcrumb
            )
        );
    }

    /**
     * Block template liste
     */
    public function lastEvenementAction(Request $request, $limit)
    {

        $evenements = $this->getDoctrine()
                           ->getRepository('EvenementBundle:Evenement')
                           ->getAllEvenements(null, $request->getLocale(), null, false, $limit);

        return $this->render( 'EvenementBundle:Include:liste.html.twig',array(
                'evenements' => $evenements
            )
        );

    }

    /**
     * Block template calendrier
     */
    public function calendrierEvenementAction(Request $request, $annee = null, $mois = null)
    {
        $agenda = $this->get('agenda.service');
        $moisi18 = $agenda->getMois();
        $joursi18 = $agenda->getJours();
        $evenementsRCalendar = array();

        /* La liste des événements pour le calendier */
        $evenementsCalendar = $this->getDoctrine()
                           ->getRepository('EvenementBundle:Evenement')
                           ->getAllEvenementsCalendrier($request->getLocale());

        /* Les 2 derniers événements pour le bloc */
        $evenementsBloc = $this->getDoctrine()
                               ->getRepository('EvenementBundle:Evenement')
                               ->getAllEvenements(null, $request->getLocale(), null, false, 2);

        /* Formater les événements pour la calendrier */
        foreach ($evenementsCalendar as $evenement){
            $evenementsRCalendar[$evenement->getDebut()->format('Y-n-j')][$evenement->getId()] = '<a href="'.$this->generateUrl('client_evenement_view',array('slug' => $evenement->getSlug(), 'id' => $evenement->getId())).'">'.$evenement->getTitre().'</a><p>'.$this->get('tool.service')->truncate($evenement->getResume(),70).'</p>';
        }

        /* Retour en ajax */
        if($request->isXmlHttpRequest()){

            return new JsonResponse(array(
                    'date' => $moisi18[$mois -1].' '.$annee,
                    'contenu' => $this->render('EvenementBundle:Include:calendrier-ajax.html.twig', array(
                        'calendrier' => $agenda->getCalendrier($annee),
                        'evenements' => $evenementsRCalendar,
                        'annee' => $annee,
                        'mois' => $mois,
                    ))->getContent()
                )
            );
        }else{
            /* Retour sans ajax */
            $annee = date('Y');
            $mois = date('n');

            return $this->render( 'EvenementBundle:Include:calendrier.html.twig',array(
                    'calendrier' => $agenda->getCalendrier($annee),
                    'evenements' => $evenementsRCalendar,
                    'evenementsBloc' => $evenementsBloc,
                    'joursi18' => $joursi18,
                    'moisi18' => $moisi18,
                    'annee' => $annee,
                    'mois' => $mois
                )
            );
        }
    }
}
