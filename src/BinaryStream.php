<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
*/

declare(strict_types=1);

namespace pocketmine\utils;

use pmmp\encoding\ByteBuffer;
use function is_string;
use function round;

class BinaryStream{
	/** @var int */
	protected $offset; //read offset only

	private ByteBuffer $byteBuffer;

	public function __construct(string $buffer = "", int $offset = 0){
		$this->byteBuffer = new ByteBuffer($buffer);
		$this->byteBuffer->setReadOffset($offset);
	}

	public function __get(string $name) : mixed{
		return match($name){
			"buffer" => $this->byteBuffer->toString(),
			"offset" => $this->byteBuffer->getReadOffset(),
			default => throw new \Error("Undefined property: " . static::class . "::$name")
		};
	}

	public function __set(string $name, mixed $value) : void{
		if($name === "buffer"){
			if(!is_string($value)){
				throw new \TypeError("Property " . static::class . "::$name expects string, " . gettype($value) . " given");
			}
			$this->byteBuffer = new ByteBuffer($value);
		}elseif($name === "offset"){
			if(!is_int($value)){
				throw new \TypeError("Property " . static::class . "::$name expects int, " . gettype($value) . " given");
			}
			$this->byteBuffer->setReadOffset($value);
		}else{
			throw new \Error("Undefined property: " . static::class . "::$name");
		}
	}

	/**
	 * Rewinds the stream pointer to the start.
	 */
	public function rewind() : void{
		$this->byteBuffer->setReadOffset(0);
	}

	public function setOffset(int $offset) : void{
		$this->byteBuffer->setReadOffset($offset);
	}

	public function getOffset() : int{
		return $this->offset;
	}

	public function getBuffer() : string{
		return $this->byteBuffer->toString();
	}

	/**
	 * @phpstan-impure
	 * @throws BinaryDataException if there are not enough bytes left in the buffer
	 *
	 * @phpstan-param int<0, max> $len
	 */
	public function get(int $len) : string{
		if($len < 0){
			throw new \InvalidArgumentException("Length must be positive");
		}

		return $this->byteBuffer->readByteArray($len);
	}

	/**
	 * @phpstan-impure
	 * @throws BinaryDataException
	 */
	public function getRemaining() : string{
		return $this->byteBuffer->readByteArray($this->byteBuffer->getUsedLength() - $this->byteBuffer->getReadOffset());
	}

	public function put(string $str) : void{
		$this->byteBuffer->writeByteArray($str);
	}

	/**
	 * @phpstan-impure
	 * @throws BinaryDataException
	 */
	public function getBool() : bool{
		return $this->byteBuffer->readUnsignedByte() !== 0;
	}

	public function putBool(bool $v) : void{
		$this->byteBuffer->writeUnsignedByte($v ? 1 : 0);
	}

	/**
	 * @phpstan-impure
	 * @throws BinaryDataException
	 */
	public function getByte() : int{
		return $this->byteBuffer->readUnsignedByte();
	}

	public function putByte(int $v) : void{
		$this->byteBuffer->writeUnsignedByte($v);
	}

	/**
	 * @phpstan-impure
	 * @throws BinaryDataException
	 */
	public function getShort() : int{
		return $this->byteBuffer->readUnsignedShortBE();
	}

	/**
	 * @phpstan-impure
	 * @throws BinaryDataException
	 */
	public function getSignedShort() : int{
		return $this->byteBuffer->readSignedShortBE();
	}

	public function putShort(int $v) : void{
		$this->byteBuffer->writeUnsignedShortBE($v);
	}

	/**
	 * @phpstan-impure
	 * @throws BinaryDataException
	 */
	public function getLShort() : int{
		return $this->byteBuffer->readUnsignedShortLE();
	}

	/**
	 * @phpstan-impure
	 * @throws BinaryDataException
	 */
	public function getSignedLShort() : int{
		return $this->byteBuffer->readSignedShortLE();
	}

	public function putLShort(int $v) : void{
		$this->byteBuffer->writeUnsignedShortLE($v);
	}

	/**
	 * @phpstan-impure
	 * @throws BinaryDataException
	 */
	public function getTriad() : int{
		return $this->byteBuffer->readUnsignedTriadBE();
	}

	public function putTriad(int $v) : void{
		$this->byteBuffer->writeUnsignedTriadBE($v);
	}

	/**
	 * @phpstan-impure
	 * @throws BinaryDataException
	 */
	public function getLTriad() : int{
		return $this->byteBuffer->readUnsignedTriadLE();
	}

	public function putLTriad(int $v) : void{
		$this->byteBuffer->writeUnsignedTriadLE($v);
	}

	/**
	 * @phpstan-impure
	 * @throws BinaryDataException
	 */
	public function getInt() : int{
		return $this->byteBuffer->readSignedIntBE(); //wow, very inconsistency!
	}

	public function putInt(int $v) : void{
		$this->byteBuffer->writeSignedIntBE($v); //wow, very inconsistency!
	}

	/**
	 * @phpstan-impure
	 * @throws BinaryDataException
	 */
	public function getLInt() : int{
		return $this->byteBuffer->readSignedIntLE(); //wow, very inconsistency!
	}

	public function putLInt(int $v) : void{
		$this->byteBuffer->writeSignedIntLE($v); //wow, very inconsistency!
	}

	/**
	 * @phpstan-impure
	 * @throws BinaryDataException
	 */
	public function getFloat() : float{
		return $this->byteBuffer->readFloatBE();
	}

	/**
	 * @deprecated
	 * @phpstan-impure
	 * @throws BinaryDataException
	 */
	public function getRoundedFloat(int $accuracy) : float{
		return round($this->getFloat(), $accuracy);
	}

	public function putFloat(float $v) : void{
		$this->byteBuffer->writeFloatBE($v);
	}

	/**
	 * @phpstan-impure
	 * @throws BinaryDataException
	 */
	public function getLFloat() : float{
		return $this->byteBuffer->readFloatLE();
	}

	/**
	 * @deprecated
	 * @phpstan-impure
	 * @throws BinaryDataException
	 */
	public function getRoundedLFloat(int $accuracy) : float{
		return round($this->getLFloat(), $accuracy);
	}

	public function putLFloat(float $v) : void{
		$this->byteBuffer->writeFloatLE($v);
	}

	/**
	 * @phpstan-impure
	 * @throws BinaryDataException
	 */
	public function getDouble() : float{
		return $this->byteBuffer->readDoubleBE();
	}

	public function putDouble(float $v) : void{
		$this->byteBuffer->writeDoubleBE($v);
	}

	/**
	 * @phpstan-impure
	 * @throws BinaryDataException
	 */
	public function getLDouble() : float{
		return $this->byteBuffer->readDoubleLE();
	}

	public function putLDouble(float $v) : void{
		$this->byteBuffer->writeDoubleLE($v);
	}

	/**
	 * @phpstan-impure
	 * @throws BinaryDataException
	 */
	public function getLong() : int{
		return $this->byteBuffer->readSignedLongBE();
	}

	public function putLong(int $v) : void{
		$this->byteBuffer->writeSignedLongBE($v);
	}

	/**
	 * @phpstan-impure
	 * @throws BinaryDataException
	 */
	public function getLLong() : int{
		return $this->byteBuffer->readSignedLongLE();
	}

	public function putLLong(int $v) : void{
		$this->byteBuffer->writeSignedLongLE($v);
	}

	/**
	 * Reads a 32-bit variable-length unsigned integer from the buffer and returns it.
	 *
	 * @phpstan-impure
	 * @throws BinaryDataException
	 */
	public function getUnsignedVarInt() : int{
		return $this->byteBuffer->readUnsignedVarInt();
	}

	/**
	 * Writes a 32-bit variable-length unsigned integer to the end of the buffer.
	 */
	public function putUnsignedVarInt(int $v) : void{
		$this->byteBuffer->writeUnsignedVarInt($v);
	}

	/**
	 * Reads a 32-bit zigzag-encoded variable-length integer from the buffer and returns it.
	 *
	 * @phpstan-impure
	 * @throws BinaryDataException
	 */
	public function getVarInt() : int{
		return $this->byteBuffer->readSignedVarInt();
	}

	/**
	 * Writes a 32-bit zigzag-encoded variable-length integer to the end of the buffer.
	 */
	public function putVarInt(int $v) : void{
		$this->byteBuffer->writeSignedVarInt($v);
	}

	/**
	 * Reads a 64-bit variable-length integer from the buffer and returns it.
	 *
	 * @phpstan-impure
	 * @throws BinaryDataException
	 */
	public function getUnsignedVarLong() : int{
		return $this->byteBuffer->readUnsignedVarLong();
	}

	/**
	 * Writes a 64-bit variable-length integer to the end of the buffer.
	 */
	public function putUnsignedVarLong(int $v) : void{
		$this->byteBuffer->writeUnsignedVarLong($v);
	}

	/**
	 * Reads a 64-bit zigzag-encoded variable-length integer from the buffer and returns it.
	 *
	 * @phpstan-impure
	 * @throws BinaryDataException
	 */
	public function getVarLong() : int{
		return $this->byteBuffer->readSignedVarLong();
	}

	/**
	 * Writes a 64-bit zigzag-encoded variable-length integer to the end of the buffer.
	 */
	public function putVarLong(int $v) : void{
		$this->byteBuffer->writeSignedVarLong($v);
	}

	/**
	 * Returns whether the read offset has reached the end of the buffer.
	 */
	public function feof() : bool{
		return $this->byteBuffer->getReadOffset() >= $this->byteBuffer->getUsedLength();
	}
}
