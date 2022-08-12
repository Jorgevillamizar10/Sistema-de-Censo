<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Serializer;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\Virus;
use App\Entity\User;

class UserController extends AbstractController
{
    private $doctrine;
    private $serializer;
    
    public function __construct( ManagerRegistry $doctrine ) {
        
        $encoders = [new JsonEncoder()];
        $normalizers = [new ObjectNormalizer()];
        
        
        $this->doctrine = $doctrine;
        $this->serializer = new Serializer($normalizers, $encoders);
    }

    /**
     * @Route("/signup", name="signup", methods="POST")
     */
    public function signup(Request $request): Response
    {
        try {
            $req = @json_decode($request->getContent(), true);
            
            $newUser = new User();
            $newUser->setDni($req['dni']);
            $newUser->setName($req['name']);
            $newUser->setEmail($req['email']);
            $newUser->setIsAdmin($req['isAdmin']);
            $newUser->setAddress($req['address']);
            $newUser->setPhone($req['cellphone']);
            $newUser->setPassword($req['password']);
            $newUser->setLastName($req['lastName']);

            $hashPassword = base64_encode($newUser->getPassword());
            $newUser->setPassword($hashPassword);
    
            $db = $this->doctrine->getManager();
            $db->persist($newUser);
            $db->flush();
            
            return new JsonResponse(array(
                'code' => 200,
                'error' => null,
                'result' => true,
                'message' => null,
            ), 200);
            
        } catch (\Exception $th) {
            return new JsonResponse(array(
                'code' => 400,
                'result' => null,
                'message' => $th->getMessage(),
                'error' => 'Error al crear usuario',
            ), 400);
        }
    }

    /**
     * @Route("/login", name="login", methods="POST")
     */
    public function login(Request $request):Response {
        $req = @json_decode($request->getContent(), true);

        $hashPassword = base64_encode($req['password']);

        $user = $this->doctrine->getRepository(User::class)->findOneBy([
            "email" => $req['email'],
            "password" => $hashPassword
        ]);

        if($user) {
            $serializedUser = $this->serializer->serialize($user, 'json');

            return new JsonResponse(array(
                'code' => 200,
                'error' => null,
                'message' => 'success',
                'result' => @json_decode($serializedUser, true),
            ));
        }
        else return new JsonResponse(array(
            'code' => 404,
            'error' => true,
            'result' => null,
            'message' => 'user not found',
        ));
    }


    /**
     * @Route("/all-virus", name="allVirus", methods="GET")
     */
    public function asignVirus(Request $request):Response {

        $allVirus = $this->doctrine->getRepository(Virus::class)->allVirus();

        // $serializedVirus = $this->serializer->serialize($allVirus, 'json');

        return new JsonResponse(array(
            'code' => $allVirus[0],
            'error' => null,
            'message' => $allVirus[1],
            'result' => $allVirus[2],
        ));
    }

    /**
     * @Route("/register-virus", name="registerVirus", methods="POST")
     */
    public function setUnknounVirus(Request $request): Response {
        $req = @json_decode($request->getContent(), true);
        $flush = true;

        try {
            $db = $this->doctrine->getManager();
            $newVirus = null;
            $user = $this->doctrine->getRepository(User::class)->find($req['userId']);

            if($req['unknoun']) {
                $newVirus = new Virus();
                $newVirus->addUser($user);
                $newVirus->setName('Desconocido');
                $newVirus->setSintomas($req['sintomas']);
            } else {
                $newVirus = $this->doctrine->getRepository(Virus::class)->findOneBy([
                    "name" => $req['name']
                ]);
                if($newVirus) {
                    $response = $this->doctrine->getRepository(User::class)->addVirus($newVirus, $user->getId());
                    $flush = false;
                    // $user->setVirus($newVirus);
                    // $newVirus->addUser($user);
                } else {
                    $newVirus = new Virus();
                    $newVirus->addUser($user);
                    $newVirus->setName($req['name']);
                    $newVirus->setSintomas($req['sintomas']);

                }
            }

            if($newVirus && $flush) {
                $db->persist($newVirus);
                $db->flush();
            }
            
            return new JsonResponse(array(
                'code' => 200,
                'error' => $response[2],
                'result' => $user->getId(),
                'message' => $newVirus->getId(),
            ));
        } catch (\Exception $th) {
            return new JsonResponse(array(
                'code' => 400,
                'result' => null,
                'message' => $th->getMessage(),
                'error' => 'Error al actualizar virus',
            ), 400);
        }
    }
}
