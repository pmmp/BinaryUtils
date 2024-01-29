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
use pmmp\encoding\DataDecodeException;
use function is_string;
use function round;

class BinaryStream{
	/** @var int */
	protected $offset; //read offset only

	private ByteBuffer $byteBuffer;

	public function __construct(string $buffer = "", int $offset = 0){
		$this->byteBuffer = new ByteBuffer();
		//we can't pass the buffer directly to the constructor, as the constructor will set the write offset to 0, which
		//would be inconsistent with the original BinaryStream implementation
		$this->byteBuffer->writeByteArray($buffer);

		$this->offset = $offset;
	}

	public function __get(string $name) : mixed{
		if($name === "buffer"){
			return $this->byteBuffer->toString();
		}

		throw new \Error("Undefined property: " . static::class . "::$name");
	}

	public function __set(string $name, mixed $value) : void{
		if($name === "buffer"){
			if(!is_string($value)){
				throw new \TypeError("Property " . static::class . "::$name expects string, " . gettype($value) . " given");
			}
			$this->byteBuffer = new ByteBuffer();
			//we can't pass the buffer directly to the constructor, as the constructor will set the write offset to 0, which
			//would be inconsistent with the original BinaryStream implementation
			$this->byteBuffer->writeByteArray($value);
			return;
		}

		throw new \Error("Undefined property: " . static::class . "::$name");
	}

	/**
	 * Rewinds the stream pointer to the start.
	 */
	public function rewind() : void{
		$this->byteBuffer->rewind();
	}

	public function setOffset(int $offset) : void{
		$this->offset = $offset;
	}

	public function getOffset() : int{
		return $this->offset;
	}

	public function getBuffer() : string{
		return $this->byteBuffer->toString();
	}

	/**
	 * This ugly hack ensures that reading doesn't change the ByteBuffer's internal offset, as that's needed if someone
	 * decides to write to the buffer. In addition, BinaryStream->setOffset() accepts invalid values that ByteBuffer
	 * doesn't, so we have to track the read offset separately anyway.
	 * @throws BinaryDataException
	 */
	private function seekReadOffset() : int{
		try{
			//ByteBuffer doesn't accept offsets beyond the end, so we need a hack to make the behaviour completely
			//consistent with the original BinaryStream implementation
			$oldOffset = $this->byteBuffer->getOffset();
			$this->byteBuffer->setOffset($this->offset);
			return $oldOffset;
		}catch(\ValueError $e){
			throw new BinaryDataException($e->getMessage(), 0, $e);
		}
	}

	private function updateOffsets(int $oldWriteOffset) : void{
		$this->offset = $this->byteBuffer->getOffset();

		$this->byteBuffer->setOffset($oldWriteOffset); //internal ByteBuffer offset is only used for writing
	}

	/**
	 * This hacky mess converts DataDecodeExceptions to BinaryDataExceptions, and also makes sure reading doesn't mess
	 * with the ByteBuffer's internal offset (to allow the old write behaviour of BinaryStream to work uninterrupted).
	 *
	 * @phpstan-template T of int|float|string
	 * @phpstan-param \Closure() : T $readFunc
	 * @phpstan-return T
	 *
	 * @throws BinaryDataException
	 */
	private function readSimple(\Closure $readFunc) : int|float|string{
		$writeOffset = $this->seekReadOffset();
		try{
			return $readFunc();
		}catch(DataDecodeException $e){
			throw new BinaryDataException($e->getMessage(), 0, $e);
		}finally{
			$this->updateOffsets($writeOffset);
		}
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

		return $this->readSimple(fn() => $this->byteBuffer->readByteArray($len));
	}

	/**
	 * @phpstan-impure
	 * @throws BinaryDataException
	 */
	public function getRemaining() : string{
		return $this->readSimple(fn() => $this->byteBuffer->readByteArray($this->byteBuffer->getUnreadLength()));
	}

	public function put(string $str) : void{
		$this->byteBuffer->writeByteArray($str);
	}

	/**
	 * @phpstan-impure
	 * @throws BinaryDataException
	 */
	public function getBool() : bool{
		return $this->readSimple($this->byteBuffer->readUnsignedByte(...)) !== 0;
	}

	public function putBool(bool $v) : void{
		$this->byteBuffer->writeUnsignedByte($v ? 1 : 0);
	}

	/**
	 * @phpstan-impure
	 * @throws BinaryDataException
	 */
	public function getByte() : int{
		return $this->readSimple($this->byteBuffer->readUnsignedByte(...));
	}

	public function putByte(int $v) : void{
		$this->byteBuffer->writeUnsignedByte($v);
	}

	/**
	 * @phpstan-impure
	 * @throws BinaryDataException
	 */
	public function getShort() : int{
		return $this->readSimple($this->byteBuffer->readUnsignedShortBE(...));
	}

	/**
	 * @phpstan-impure
	 * @throws BinaryDataException
	 */
	public function getSignedShort() : int{
		return $this->readSimple($this->byteBuffer->readSignedShortBE(...));
	}

	public function putShort(int $v) : void{
		$this->byteBuffer->writeUnsignedShortBE($v);
	}

	/**
	 * @phpstan-impure
	 * @throws BinaryDataException
	 */
	public function getLShort() : int{
		return $this->readSimple($this->byteBuffer->readUnsignedShortLE(...));
	}

	/**
	 * @phpstan-impure
	 * @throws BinaryDataException
	 */
	public function getSignedLShort() : int{
		return $this->readSimple($this->byteBuffer->readSignedShortLE(...));
	}

	public function putLShort(int $v) : void{
		$this->byteBuffer->writeUnsignedShortLE($v);
	}

	/**
	 * @phpstan-impure
	 * @throws BinaryDataException
	 */
	public function getTriad() : int{
		return $this->readSimple($this->byteBuffer->readUnsignedTriadBE(...));
	}

	public function putTriad(int $v) : void{
		$this->byteBuffer->writeUnsignedTriadBE($v);
	}

	/**
	 * @phpstan-impure
	 * @throws BinaryDataException
	 */
	public function getLTriad() : int{
		return $this->readSimple($this->byteBuffer->readUnsignedTriadLE(...));
	}

	public function putLTriad(int $v) : void{
		$this->byteBuffer->writeUnsignedTriadLE($v);
	}

	/**
	 * @phpstan-impure
	 * @throws BinaryDataException
	 */
	public function getInt() : int{
		return $this->readSimple($this->byteBuffer->readSignedIntBE(...)); //wow, very inconsistency!
	}

	public function putInt(int $v) : void{
		$this->byteBuffer->writeSignedIntBE($v); //wow, very inconsistency!
	}

	/**
	 * @phpstan-impure
	 * @throws BinaryDataException
	 */
	public function getLInt() : int{
		return $this->readSimple($this->byteBuffer->readSignedIntLE(...)); //wow, very inconsistency!
	}

	public function putLInt(int $v) : void{
		$this->byteBuffer->writeSignedIntLE($v); //wow, very inconsistency!
	}

	/**
	 * @phpstan-impure
	 * @throws BinaryDataException
	 */
	public function getFloat() : float{
		return $this->readSimple($this->byteBuffer->readFloatBE(...));
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
		return $this->readSimple($this->byteBuffer->readFloatLE(...));
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
		return $this->readSimple($this->byteBuffer->readDoubleBE(...));
	}

	public function putDouble(float $v) : void{
		$this->byteBuffer->writeDoubleBE($v);
	}

	/**
	 * @phpstan-impure
	 * @throws BinaryDataException
	 */
	public function getLDouble() : float{
		return $this->readSimple($this->byteBuffer->readDoubleLE(...));
	}

	public function putLDouble(float $v) : void{
		$this->byteBuffer->writeDoubleLE($v);
	}

	/**
	 * @phpstan-impure
	 * @throws BinaryDataException
	 */
	public function getLong() : int{
		return $this->readSimple($this->byteBuffer->readSignedLongBE(...));
	}

	public function putLong(int $v) : void{
		$this->byteBuffer->writeSignedLongBE($v);
	}

	/**
	 * @phpstan-impure
	 * @throws BinaryDataException
	 */
	public function getLLong() : int{
		return $this->readSimple($this->byteBuffer->readSignedLongLE(...));
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
		return $this->readSimple($this->byteBuffer->readUnsignedVarInt(...));
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
		return $this->readSimple($this->byteBuffer->readSignedVarInt(...));
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
		return $this->readSimple($this->byteBuffer->readUnsignedVarLong(...));
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
		return $this->readSimple($this->byteBuffer->readSignedVarLong(...));
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
		//TODO: this would be much simpler if ByteBuffer had a method to get the total length of its internal buffer
		//we don't want to use toString() because it copies the buffer, which is a performance hit
		$writeOffset = $this->byteBuffer->getOffset();
		try{
			$this->byteBuffer->setOffset($this->offset);
			$result = $this->byteBuffer->getUnreadLength() === 0;
			$this->byteBuffer->setOffset($writeOffset);
			return $result;
		}catch(\ValueError $e){
			return true;
		}
	}
}
