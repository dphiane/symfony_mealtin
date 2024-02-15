<?php

namespace App\Controller;

use App\Entity\Disponibility;
use App\Entity\Reservation;
use App\Form\ReservationType;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\ReservationRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class ReservationController extends AbstractController
{
    #[Route('/reservation', name: 'app_reservation')]
    public function index(Request $request, EntityManagerInterface $entityManagerInterface, ReservationRepository $reservationRepository): Response
    {
        $reservation = new Reservation();
        $form = $this->createForm(ReservationType::class, $reservation);
        $form->handleRequest($request);
        $user = $this->getUser();
        if ($form->isSubmitted() && $form->isValid()) {
            $date = $reservation->getDate();
            $reservations = $reservationRepository->findOneBy(['date' => $date]);

            if ($reservations) {
                $disponibility = $reservations->getDisponibility();
                $reservationTime = $reservation->getTime()->format("H:i:s");

                $disponibilityReservationLunch = $disponibility->getMaxReservationLunch();
                $disponibilityReservationDiner = $disponibility->getMaxReservationDiner();
                $disponibilityMaxSeatDiner = $disponibility->getMaxSeatDiner();
                $disponibilityMaxSeatLunch = $disponibility->getMaxSeatLunch();

                $howManyGuest= $reservation->getHowManyGuest();
                if ($reservationTime < "14:00:00") {
                    if($disponibilityMaxSeatLunch - $howManyGuest < 0 || $disponibilityReservationLunch - 1 <0){
                        $this->addFlash('warning','Malheursement nous n\'avons pas assez de place');
                        $this->redirectToRoute('app_reservation');
                        exit;
                    }
                    $disponibility
                        ->setMaxReservationLunch($disponibilityReservationLunch - 1)
                        ->setMaxSeatLunch($disponibilityMaxSeatLunch - $howManyGuest);
                } else {
                    if ($disponibilityMaxSeatDiner - $howManyGuest < 0 || $disponibilityReservationDiner - 1 < 0) {
                        $this->addFlash('warning', 'Malheursement nous n\'avons pas assez de place');
                        $this->redirectToRoute('app_reservation');
                        exit;
                    }
                    $disponibility
                        ->setMaxReservationDiner($disponibilityReservationDiner - 1)
                        ->setMaxSeatDiner($disponibilityMaxSeatDiner - $howManyGuest);
                }
            } else {
                // Si aucune réservation n'est trouvée, créez une nouvelle disponibilité
                $disponibility = new Disponibility();
                $disponibility->setMaxReservationDiner(13)
                    ->setMaxSeatLunch(40)
                    ->setMaxReservationLunch(13)
                    ->setMaxSeatDiner(40);
            }
            // Attribuez cette disponibilité à la réservation créée
            $reservation->setDisponibility($disponibility);
            $entityManagerInterface->persist($disponibility);
            $entityManagerInterface->flush();
            $reservation->setUser($user);
            $reservation->setDisponibility($disponibility);
            $entityManagerInterface->persist($reservation);
            $entityManagerInterface->flush();

            $this->addFlash("success", "Votre réservation a bien été enregistrée");
            $this->redirectToRoute('app_home');
        }

        return $this->render('reservation/index.html.twig', [
            'form' => $form,
        ]);
    }
}
