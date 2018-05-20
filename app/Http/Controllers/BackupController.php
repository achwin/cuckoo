<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class BackupController extends Controller
{
	protected $jumlahIterasi;
    protected $jumlahSolusi;
    protected $jumlahKota;
    protected $pa;

    public function index()
    {
    	$this->jumlahIterasi = 10;
    	$this->pa = 0.25;
    	$tsp = [
    		'1' => [
    			'1' => 0,
    			'2' => 29,
    			'3' => 82,
    			'4' => 46,
    			'5' => 68,
    		],
    		'2' => [
    			'1' => 29,
    			'2' => 0,
    			'3' => 55,
    			'4' => 46,
    			'5' => 42,
    		],
    		'3' => [
    			'1' => 82,
    			'2' => 55,
    			'3' => 0,
    			'4' => 68,
    			'5' => 46,
    		],
    		'4' => [
    			'1' => 46,
    			'2' => 46,
    			'3' => 68,
    			'4' => 0,
    			'5' => 82,
    		],
    		'5' => [
    			'1' => 68,
    			'2' => 42,
    			'3' => 46,
    			'4' => 82,
    			'5' => 0,
    		],
    	];
    	$this->jumlahSolusi = 4;
    	$this->jumlahKota = count($tsp);

    	// Langkah 1 Generate random
    	$randomRoutes = $this->randomRoutes($tsp,$this->jumlahSolusi);

    	// Langkah 2 Pengkodean random routes
    	$decodedRoutes = $this->decoded($randomRoutes,$this->jumlahSolusi);

    	// Langkah 3 Add fitness in route
    	$routesWithFitness = $this->fitnessRoute($tsp,$decodedRoutes,$this->jumlahSolusi);
    	for ($i=1; $i <= $this->jumlahIterasi; $i++) { 
	    	// Langkah 4a Get best and worst fitness
	    	$worstFitness = $this->getWorstFitness($routesWithFitness);
	    	$bestFitness  = $this->getBestFitness($routesWithFitness);

	    	// Langkah 4b Get stepsize
	    	$stepSize = $this->stepSize($routesWithFitness,$worstFitness,$bestFitness,$i);

	    	// Langkah 4c Generate random and make stepsize route
	    	$newRandomRoutes = $this->randomRoutes($tsp, $this->jumlahSolusi);
	    	$stepSizeRoutes = $this->stepSizeRoutes($randomRoutes,$newRandomRoutes,$stepSize);
	    	$decodedStepSizeRoutes = $this->decoded($stepSizeRoutes,$this->jumlahSolusi);
	    	$stepSizeRoutesWithFitness = $this->fitnessRoute($tsp,$decodedStepSizeRoutes,$this->jumlahSolusi);
	    	$comparedRoutes = $this->compareFitness($routesWithFitness,$stepSizeRoutesWithFitness);
	    	if ((mt_rand() / mt_getrandmax()) > $this->pa) {
	    		$worstFitnessComparedRoutes = $this->getWorstFitness($comparedRoutes);
	    		$worstPosition = array_search($worstFitnessComparedRoutes, $comparedRoutes);
	    		$newRoute = $this->randomRoutes($tsp,1);
	    		$decodedNewRoute = $this->decoded($newRoute,1);
	    		$newRouteWithFitness = $this->fitnessRoute($tsp,$decodedNewRoute,1);
	    		$comparedRoutes[$worstPosition] = $newRouteWithFitness['rute1'];
	    	}
	    	$bestFitnessComparedRoutes = $this->getBestFitness($comparedRoutes);
	    	$bestFitnessPosition = array_search($bestFitnessComparedRoutes, $comparedRoutes);
	    	$bestRoute['Iterasi'.$i][$bestFitnessPosition] = $bestFitnessComparedRoutes;
	    	$routesWithFitness = $comparedRoutes;
	    }
	    dd($bestRoute);
    }

    public function randomRoutes($tsp, $jumlahSolusi)
    {
    	$rute = [];
    	for ($i=1; $i <= $jumlahSolusi; $i++) { 
    		// Mencari random tiap rute
    		for ($kota=1; $kota <= $this->jumlahKota; $kota++) { 
    			$rute['rute'.$i][$kota] = 1 + (($this->jumlahKota - 1) * (mt_rand() / mt_getrandmax()));
    		}
    	}
    	return $rute;
    }

    public function fitnessRoute($tsp,$routes,$jumlahSolusi)
    {
    	$routesWithFitness = [];
    	// $routes = [
    	// 	'rute1' => [
    	// 		1 => 3,
    	// 		2 => 4,
    	// 		3 => 2,
    	// 		4 => 1,
    	// 		5 => 5,
    	// 	],
    	// 	'rute2' => [
    	// 		1 => 3,
    	// 		2 => 4,
    	// 		3 => 5,
    	// 		4 => 2,
    	// 		5 => 1,
    	// 	],
    	// 	'rute3' => [
    	// 		1 => 2,
    	// 		2 => 5,
    	// 		3 => 4,
    	// 		4 => 3,
    	// 		5 => 1,
    	// 	],
    	// 	'rute4' => [
    	// 		1 => 1,
    	// 		2 => 5,
    	// 		3 => 3,
    	// 		4 => 2,
    	// 		5 => 4,
    	// 	],
    	// ];
    	for ($solusi=1; $solusi <= $jumlahSolusi; $solusi++) {
    		$sum = 0; 
    		for ($kota=1; $kota <= $this->jumlahKota; $kota++) { 
    			if ($kota < $this->jumlahKota) {
    				$routesWithFitness['rute'.$solusi][$kota] = 
    				$tsp[$routes['rute'.$solusi][$kota]][$routes['rute'.$solusi][$kota+1]];
    			}
    			else{
    				$routesWithFitness['rute'.$solusi][$kota] = 
    				$tsp[$routes['rute'.$solusi][$kota]][$routes['rute'.$solusi][1]];
    			}
    			$sum = $sum + $routesWithFitness['rute'.$solusi][$kota];
    		}
    		$routesWithFitness['rute'.$solusi]['fitness'] = $sum;
    	}
    	return $routesWithFitness;
    }

    public function getWorstFitness($routesWithFitness)
    {
    	usort($routesWithFitness, function($a, $b) {
            return $a['fitness'] < $b['fitness'];
        });
        
        return $routesWithFitness[0];
    }

    public function getBestFitness($routesWithFitness)
    {
    	usort($routesWithFitness, function($a, $b) {
            return $a['fitness'] > $b['fitness'];
        });
        
        return $routesWithFitness[0];
    }

    public function stepSize($routesWithFitness,$worstFitness,$bestFitness,$iterasi)
    {
    	for ($i=1; $i <= $this->jumlahSolusi; $i++) { 
    		$step[$i] = pow((1/$iterasi), 
    			abs((($bestFitness['fitness']-$routesWithFitness['rute'.$i]['fitness']) / 
    				($bestFitness['fitness']-$worstFitness['fitness']))
    			));
    	}
    	return $step;
    }

    public function decoded($routes,$jumlahSolusi)
    {
	    for ($i=1; $i <= $jumlahSolusi; $i++) { 
			// Mencari random tiap rute
			for ($kota=1; $kota <= $this->jumlahKota; $kota++) { 
				$rank['rute'.$i][$kota] = $routes['rute'.$i][$kota];
			}
			// Mencari rank
			rsort($rank['rute'.$i]);
			for ($kota=1; $kota <= $this->jumlahKota; $kota++) { 
				$routes['rute'.$i][$kota] = array_search($routes['rute'.$i][$kota], $rank['rute'.$i]) + 1;
				}
    	}
    	return $routes;
    }

    public function stepSizeRoutes($routes,$newRoutes,$stepSize)
    {
    	for ($rute=1; $rute <= $this->jumlahSolusi; $rute++) { 
    		for ($kota=1; $kota <= $this->jumlahKota; $kota++) { 
    			$stepSizeRoutes['rute'.$rute][$kota] = 
    			$routes['rute'.$rute][$kota] + $newRoutes['rute'.$rute][$kota] * $stepSize[$rute];
    		}
    	}
    	return $stepSizeRoutes;
    }

    public function compareFitness($routesWithFitness,$stepSizeRoutesWithFitness)
    {
    	for ($rute=1; $rute <= $this->jumlahSolusi; $rute++) {
    		$random = rand(1,$this->jumlahSolusi); 
    		if ($routesWithFitness['rute'.$rute]['fitness'] > $stepSizeRoutesWithFitness['rute'.$random]['fitness']) {
    			$comparedRoutes['rute'.$rute] = $stepSizeRoutesWithFitness['rute'.$random];
    		}
    		else{
    			$comparedRoutes['rute'.$rute] = $routesWithFitness['rute'.$rute];
    		}
    	}
    	return $comparedRoutes;
    }

    public function encoded($routes,$jumlahSolusi)
    {
    	
    }
}
